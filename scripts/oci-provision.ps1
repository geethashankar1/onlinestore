# scripts/oci-provision.ps1
# Windows/PowerShell twin of oci-provision.sh, for running the instance-launch
# retry loop from your own machine instead of Cloud Shell (which disconnects
# after a while, killing a long retry).
#
# Free ARM capacity in a single-AD region like ap-hyderabad-1 can take hours or
# days to appear. This keeps asking, logs every attempt with a timestamp, and
# stops the moment it succeeds.
#
# Prerequisites:
#   python -m pip install --user oci-cli
#   oci setup bootstrap          # browser login; uploads the API key for you
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts\oci-provision.ps1
#
# Re-runnable: existing VCN/subnet/keys are reused, never duplicated.

param(
    [string]$Name         = "eshop",
    [int]   $Ocpus        = 1,
    [int]   $MemoryGb     = 6,
    # 5 minutes, not 30s: Oracle rate-limits launch_instance per user and
    # answers a hammered endpoint with 429 TooManyRequests, which buys nothing.
    [int]   $RetrySeconds = 300,
    [int]   $MaxBackoffSeconds = 3600,
    [string]$KeyPath      = "$env:USERPROFILE\.ssh\eshop_key",
    [string]$LogFile      = "$env:USERPROFILE\.ssh\eshop-provision.log"
)

$ErrorActionPreference = "Stop"
$tmp = Join-Path $env:TEMP "oci-provision"
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Path $tmp -Force | Out-Null }

function Write-Log {
    param([string]$Message, [string]$Colour = "Gray")
    $line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    Write-Host $line -ForegroundColor $Colour
    Add-Content -Path $LogFile -Value $line -Encoding utf8
}

function Step { param([string]$m) Write-Log "==> $m" "Cyan" }
function Ok   { param([string]$m) Write-Log "    OK: $m" "Green" }

# OCI CLI complex parameters are passed as files to dodge PowerShell's native-
# command quote mangling. Paths use forward slashes for the file:// scheme.
function New-JsonParam {
    param([string]$FileName, [string]$Json)
    $path = Join-Path $tmp $FileName
    [System.IO.File]::WriteAllText($path, $Json)
    return "file://" + ($path -replace '\\', '/')
}

function Invoke-Oci {
    # Runs the CLI and returns trimmed stdout; throws the FULL stderr on failure.
    # $ErrorActionPreference must drop to Continue around the call: under "Stop",
    # PowerShell turns the first stderr line of a native command into a
    # terminating NativeCommandError and discards the rest of the message.
    param([string[]]$OciArgs)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $raw  = & oci @OciArgs 2>&1
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
    }
    $text = (@($raw) | ForEach-Object { $_.ToString() }) -join "`n"
    if ($code -ne 0) { throw $text }
    return $text.Trim()
}

if (-not (Get-Command oci -ErrorAction SilentlyContinue)) {
    Write-Host "ERROR: 'oci' not on PATH. Install with: python -m pip install --user oci-cli" -ForegroundColor Red
    Write-Host "Then reopen the terminal, or add this to PATH:" -ForegroundColor Yellow
    Write-Host "  $env:APPDATA\Python\Python311\Scripts" -ForegroundColor Yellow
    exit 1
}

Step "Reading tenancy and availability domains"
$adJson  = Invoke-Oci @("iam","availability-domain","list")
$adData  = ($adJson | ConvertFrom-Json).data
$compartmentId = $adData[0].'compartment-id'
$ads = @($adData | ForEach-Object { $_.name })
Ok "Compartment: $($compartmentId.Substring(0,[Math]::Min(40,$compartmentId.Length)))..."
Ok "Availability domains ($($ads.Count)): $($ads -join ', ')"

# ── VCN ──────────────────────────────────────────────────────
Step "Virtual cloud network"
$vcnId = ""
try {
    $vcnId = Invoke-Oci @("network","vcn","list","--compartment-id",$compartmentId,
                          "--display-name","$Name-vcn","--query","data[0].id","--raw-output")
} catch { $vcnId = "" }

if ([string]::IsNullOrWhiteSpace($vcnId) -or $vcnId -eq "null") {
    $vcnId = Invoke-Oci @("network","vcn","create","--compartment-id",$compartmentId,
                          "--cidr-block","10.0.0.0/16","--display-name","$Name-vcn",
                          "--dns-label","${Name}vcn","--wait-for-state","AVAILABLE",
                          "--query","data.id","--raw-output")
    Ok "Created $Name-vcn"
} else {
    Ok "Reusing existing $Name-vcn"
}

# ── Internet gateway ─────────────────────────────────────────
Step "Internet gateway"
$igwId = ""
try {
    $igwId = Invoke-Oci @("network","internet-gateway","list","--compartment-id",$compartmentId,
                          "--vcn-id",$vcnId,"--query","data[0].id","--raw-output")
} catch { $igwId = "" }

if ([string]::IsNullOrWhiteSpace($igwId) -or $igwId -eq "null") {
    $igwId = Invoke-Oci @("network","internet-gateway","create","--compartment-id",$compartmentId,
                          "--vcn-id",$vcnId,"--is-enabled","true","--display-name","$Name-igw",
                          "--wait-for-state","AVAILABLE","--query","data.id","--raw-output")
    Ok "Created $Name-igw"
} else {
    Ok "Reusing existing gateway"
}

# ── Route table ──────────────────────────────────────────────
# Hyphenated JMESPath keys can't be passed via --query: PowerShell strips the
# inner quotes before oci.exe sees them and JMESPath rejects the bare hyphens.
# Fetch the whole object and pick the fields out here instead.
Step "Route table (0.0.0.0/0 -> internet gateway)"
$vcn   = (Invoke-Oci @("network","vcn","get","--vcn-id",$vcnId) | ConvertFrom-Json).data
$rtId  = $vcn.'default-route-table-id'
$slId  = $vcn.'default-security-list-id'
$routeParam = New-JsonParam "routes.json" ('[{"destination":"0.0.0.0/0","destinationType":"CIDR_BLOCK","networkEntityId":"' + $igwId + '"}]')
Invoke-Oci @("network","route-table","update","--rt-id",$rtId,"--route-rules",$routeParam,"--force") | Out-Null
Ok "Default route set"

# ── Security list ────────────────────────────────────────────
Step "Security list (22 SSH, 80/443 web, 8080 staging, 8090 Jenkins)"
$rules = @(22,80,443,8080,8090) | ForEach-Object {
    '{"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":' + $_ + ',"max":' + $_ + '}}}'
}
$ingressParam = New-JsonParam "ingress.json" ("[" + ($rules -join ",") + "]")
Invoke-Oci @("network","security-list","update","--security-list-id",$slId,
             "--ingress-security-rules",$ingressParam,"--force") | Out-Null
Ok "Ingress rules applied"

# ── Subnet ───────────────────────────────────────────────────
Step "Public subnet"
$subnetId = ""
try {
    $subnetId = Invoke-Oci @("network","subnet","list","--compartment-id",$compartmentId,"--vcn-id",$vcnId,
                             "--display-name","$Name-public-subnet","--query","data[0].id","--raw-output")
} catch { $subnetId = "" }

if ([string]::IsNullOrWhiteSpace($subnetId) -or $subnetId -eq "null") {
    $slParam = New-JsonParam "seclists.json" ('["' + $slId + '"]')
    $subnetId = Invoke-Oci @("network","subnet","create","--compartment-id",$compartmentId,"--vcn-id",$vcnId,
                             "--cidr-block","10.0.1.0/24","--display-name","$Name-public-subnet",
                             "--route-table-id",$rtId,"--security-list-ids",$slParam,
                             "--prohibit-public-ip-on-vnic","false","--wait-for-state","AVAILABLE",
                             "--query","data.id","--raw-output")
    Ok "Created $Name-public-subnet"
} else {
    Ok "Reusing existing subnet"
}

# ── SSH key ──────────────────────────────────────────────────
Step "SSH key pair"
$sshDir = Split-Path $KeyPath -Parent
if (-not (Test-Path $sshDir)) { New-Item -ItemType Directory -Path $sshDir -Force | Out-Null }
if (Test-Path $KeyPath) {
    Ok "Reusing $KeyPath"
} else {
    & ssh-keygen -t rsa -b 2048 -f $KeyPath -N '""' -q
    if (-not (Test-Path $KeyPath)) { throw "ssh-keygen failed to create $KeyPath" }
    Ok "Generated $KeyPath"
}

# ── Image ────────────────────────────────────────────────────
Step "Ubuntu 22.04 ARM image"
$imageId = Invoke-Oci @("compute","image","list","--compartment-id",$compartmentId,
                        "--operating-system","Canonical Ubuntu","--operating-system-version","22.04",
                        "--shape","VM.Standard.A1.Flex","--sort-by","TIMECREATED","--sort-order","DESC",
                        "--query","data[0].id","--raw-output")
if ([string]::IsNullOrWhiteSpace($imageId) -or $imageId -eq "null") { throw "No Ubuntu 22.04 ARM image found" }
Ok "$($imageId.Substring(0,[Math]::Min(40,$imageId.Length)))..."

# ── Already running? ─────────────────────────────────────────
$instanceId = ""
try {
    $instanceId = Invoke-Oci @("compute","instance","list","--compartment-id",$compartmentId,
                               "--display-name","$Name-server","--lifecycle-state","RUNNING",
                               "--query","data[0].id","--raw-output")
} catch { $instanceId = "" }

if (-not [string]::IsNullOrWhiteSpace($instanceId) -and $instanceId -ne "null") {
    Step "Instance already running - skipping launch"
} else {
    Step "Launching $Ocpus OCPU / $MemoryGb GB ARM instance"
    Write-Log "Retrying every ${RetrySeconds}s across $($ads.Count) AD(s). Ctrl+C to stop; re-run to resume." "Yellow"
    Write-Log "Log file: $LogFile" "Yellow"

    $shapeParam = New-JsonParam "shape.json" ('{"ocpus":' + $Ocpus + ',"memoryInGBs":' + $MemoryGb + '}')
    $attempt = 0
    $launched = $false
    $wait = $RetrySeconds

    while (-not $launched) {
        foreach ($ad in $ads) {
            $attempt++
            try {
                $instanceId = Invoke-Oci @("compute","instance","launch",
                    "--compartment-id",$compartmentId,
                    "--availability-domain",$ad,
                    "--shape","VM.Standard.A1.Flex",
                    "--shape-config",$shapeParam,
                    "--display-name","$Name-server",
                    "--image-id",$imageId,
                    "--subnet-id",$subnetId,
                    "--assign-public-ip","true",
                    "--ssh-authorized-keys-file","$KeyPath.pub",
                    "--wait-for-state","RUNNING",
                    "--query","data.id","--raw-output")
                Write-Log "attempt $attempt - $ad - LAUNCHED" "Green"
                $launched = $true
                break
            } catch {
                $err = $_.Exception.Message
                if ($err -match "Out of host capacity") {
                    # Normal case: no free ARM hosts right now. Keep the steady pace.
                    Write-Log "attempt $attempt - $ad - no capacity"
                    $wait = $RetrySeconds
                } elseif ($err -match "TooManyRequests|429") {
                    # We are being throttled; backing off is the only thing that helps.
                    $wait = [Math]::Min($wait * 2, $MaxBackoffSeconds)
                    Write-Log "attempt $attempt - $ad - rate limited (429), backing off to ${wait}s" "Yellow"
                } else {
                    Write-Log "attempt $attempt - $ad - FAILED (not a capacity or rate-limit error)" "Red"
                    Write-Host $err -ForegroundColor Red
                    exit 1
                }
            }
        }
        if (-not $launched) {
            Write-Log "sleeping ${wait}s before next attempt"
            Start-Sleep -Seconds $wait
        }
    }
}

# ── Public IP ────────────────────────────────────────────────
Step "Fetching public IP"
$vnics = (Invoke-Oci @("compute","instance","list-vnics","--instance-id",$instanceId) | ConvertFrom-Json).data
$publicIp = $vnics[0].'public-ip'

Write-Host ""
Write-Host "======================================================" -ForegroundColor Green
Write-Host " Instance is up." -ForegroundColor Green
Write-Host ""
Write-Host "   Public IP : $publicIp"
Write-Host "   SSH       : ssh -i `"$KeyPath`" ubuntu@$publicIp"
Write-Host ""
Write-Host " NEXT STEPS"
Write-Host "  1. ssh -i `"$KeyPath`" ubuntu@$publicIp"
Write-Host "  2. curl -fsSL https://raw.githubusercontent.com/geethashankar1/onlinestore/main/scripts/setup-server-oci.sh | sudo bash"
Write-Host "  3. Put $publicIp into the Jenkinsfile as SERVER_IP, commit, push."
Write-Host ""
Write-Host " Full runbook: DEPLOY_OCI.md" -ForegroundColor Green
Write-Host "======================================================" -ForegroundColor Green

Write-Log "PROVISIONED public-ip=$publicIp instance=$instanceId" "Green"
