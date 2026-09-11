# Deploying to Oracle Cloud (OCI) — migration from DigitalOcean

The stack is unchanged: Docker Compose runs Jenkins, Nginx, PHP/Apache, MySQL and
Certbot on one host, and Jenkins deploys to that same host it runs on. Only the
host moves.

**What actually differs from the DigitalOcean droplet:**

| | DigitalOcean | OCI |
|---|---|---|
| SSH user | `root` | `ubuntu` (root login disabled) |
| CPU | x86_64 | ARM64 (Ampere A1) — images rebuild as `arm64` |
| Firewall | UFW on the box | VCN Security List **and** the instance's own iptables |
| Cost | paid | Always Free (4 OCPU / 24 GB ARM allowance) |

The two that cause real problems are the **iptables rules** (step 3) and **ARM**
(see Troubleshooting). Everything else is the same commands you already know.

---

## Phase 1 — Create the instance

Free ARM capacity is scarce in small regions. If `ap-hyderabad-1` keeps returning
`Out of host capacity`, subscribe to a larger region first: region menu (top-right)
→ **Manage Regions** → **Subscribe** to **US East (Ashburn)**, then switch to it.

Open **Cloud Shell** (the `>_` icon). If a tutorial menu appears, press `q` until
you get a plain `$` prompt. Paste these **one line at a time** — Cloud Shell mangles
multi-line pastes.

```bash
COMPARTMENT_ID=$(oci iam availability-domain list --query "data[0].\"compartment-id\"" --raw-output)
```
```bash
AD=$(oci iam availability-domain list --query "data[0].name" --raw-output)
```
```bash
VCN_ID=$(oci network vcn create --compartment-id $COMPARTMENT_ID --cidr-block 10.0.0.0/16 --display-name eshop-vcn --dns-label eshopvcn --wait-for-state AVAILABLE --query "data.id" --raw-output)
```
```bash
IGW_ID=$(oci network internet-gateway create --compartment-id $COMPARTMENT_ID --vcn-id $VCN_ID --is-enabled true --display-name eshop-igw --wait-for-state AVAILABLE --query "data.id" --raw-output)
```
```bash
RT_ID=$(oci network vcn get --vcn-id $VCN_ID --query "data.\"default-route-table-id\"" --raw-output)
```
```bash
oci network route-table update --rt-id $RT_ID --route-rules '[{"destination": "0.0.0.0/0", "destinationType": "CIDR_BLOCK", "networkEntityId": "'"$IGW_ID"'"}]' --force
```
```bash
SL_ID=$(oci network vcn get --vcn-id $VCN_ID --query "data.\"default-security-list-id\"" --raw-output)
```

Ingress rules for 22 / 80 / 443 / 8080 / 8090 — paste the whole heredoc at once:

```bash
cat > /tmp/ingress.json << 'EOF'
[
  {"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":22,"max":22}}},
  {"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":80,"max":80}}},
  {"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":443,"max":443}}},
  {"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":8080,"max":8080}}},
  {"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,"tcpOptions":{"destinationPortRange":{"min":8090,"max":8090}}}
]
EOF
```
```bash
oci network security-list update --security-list-id $SL_ID --ingress-security-rules file:///tmp/ingress.json --force
```
```bash
SUBNET_ID=$(oci network subnet create --compartment-id $COMPARTMENT_ID --vcn-id $VCN_ID --cidr-block 10.0.1.0/24 --display-name eshop-public-subnet --route-table-id $RT_ID --security-list-ids "[\"$SL_ID\"]" --prohibit-public-ip-on-vnic false --wait-for-state AVAILABLE --query "data.id" --raw-output)
```
```bash
ssh-keygen -t rsa -b 2048 -f ~/eshop_key -N ""
```
```bash
IMAGE_ID=$(oci compute image list --compartment-id $COMPARTMENT_ID --operating-system "Canonical Ubuntu" --operating-system-version "22.04" --shape "VM.Standard.A1.Flex" --sort-by TIMECREATED --sort-order DESC --query "data[0].id" --raw-output)
```

Sanity-check that nothing is blank before launching:

```bash
echo "C=$COMPARTMENT_ID | AD=$AD | VCN=$VCN_ID | SUBNET=$SUBNET_ID | IMG=$IMAGE_ID"
```

Launch, retrying automatically through capacity errors (single line):

```bash
until oci compute instance launch --compartment-id $COMPARTMENT_ID --availability-domain "$AD" --shape "VM.Standard.A1.Flex" --shape-config '{"ocpus":1,"memoryInGBs":6}' --display-name eshop-server --image-id $IMAGE_ID --subnet-id $SUBNET_ID --assign-public-ip true --ssh-authorized-keys-file ~/eshop_key.pub --wait-for-state RUNNING; do echo "retrying in 30s..."; sleep 30; done
```

Get the public IP:

```bash
oci compute instance list-vnics --instance-id $(oci compute instance list --compartment-id $COMPARTMENT_ID --display-name eshop-server --lifecycle-state RUNNING --query "data[0].id" --raw-output) --query "data[0].\"public-ip\"" --raw-output
```

**Save the private key off Cloud Shell** — `cat ~/eshop_key`, copy the output into a
local file (e.g. `C:\Users\Geetha shankar\.ssh\eshop_key`). Cloud Shell storage is
not a backup.

---

## Phase 2 — Bootstrap the server

From your own machine (Git Bash):

```bash
chmod 600 ~/.ssh/eshop_key
ssh -i ~/.ssh/eshop_key ubuntu@<PUBLIC_IP>
```

Then, on the server:

```bash
curl -fsSL https://raw.githubusercontent.com/geethashankar1/onlinestore/main/scripts/setup-server-oci.sh | sudo bash
```

That script installs Docker + Compose, **opens ports 80/443/8080/8090 in the
instance's iptables and persists them**, clones the repo to `/opt/eshop`, generates
`/opt/eshop/.env` with fresh DB passwords, and installs the nightly nginx-reload cron.

Log out and back in afterwards so your `docker` group membership takes effect.

> **Why the iptables step matters:** OCI Ubuntu images REJECT every inbound port
> except 22 at the OS level. Opening a port in the Security List alone leaves the
> site unreachable with no error anywhere — the packets are dropped on the box.

---

## Phase 3 — Start Jenkins

```bash
cd /opt/eshop
docker compose -f docker-compose.jenkins.yml up -d
docker exec eshop_jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

Open `http://<PUBLIC_IP>:8090`, paste that password, install suggested plugins,
create your admin user. Then:

1. **Add GHCR credentials** — Manage Jenkins → Credentials → System → Global →
   Add Credentials. Kind: *Username with password*, ID exactly **`ghcr-credentials`**
   (the Jenkinsfile looks this ID up), username = your GitHub username, password =
   a GitHub PAT with `write:packages`.
2. **Create the pipeline** — New Item → *Multibranch Pipeline* → add your GitHub
   repo as the branch source → Save. It will discover `main` and `develop` and read
   the `Jenkinsfile` from each.
3. **Webhook (optional)** — GitHub repo → Settings → Webhooks → Add:
   `http://<PUBLIC_IP>:8090/github-webhook/`, content type `application/json`.
   Without it, Jenkins polls/builds only when triggered manually.

---

## Phase 4 — Point the pipeline at the new host

In [`Jenkinsfile`](Jenkinsfile), replace the placeholder with the real IP:

```groovy
SERVER_IP = 'REPLACE_WITH_OCI_PUBLIC_IP'
```

Commit and push — that is the only code change the migration needs. Both deploy
stages read `$STAGING_URL` / `$PROD_URL` from that one value.

---

## Phase 5 — DNS and SSL

At your domain registrar for `myeshopstore.online`, update the **A records** to the
new OCI public IP (delete the old droplet IP `64.227.187.60`):

| Type | Host | Value |
|---|---|---|
| A | `@` | `<OCI_PUBLIC_IP>` |
| A | `www` | `<OCI_PUBLIC_IP>` |

Wait for propagation (`nslookup myeshopstore.online` should return the new IP — can
take minutes to a few hours), **then** issue certificates. Certbot validates over
HTTP, so it fails if DNS still points at the dead droplet:

```bash
cd /opt/eshop
docker compose -f docker-compose.prod.yml up -d
bash scripts/ssl-init.sh myeshopstore.online you@example.com
```

`ssl-init.sh` issues the cert, swaps `nginx/default.conf` for the HTTPS config, and
reloads nginx.

---

## Phase 6 — Verify

```bash
docker ps                                        # all containers Up
curl -I http://<PUBLIC_IP>:8080                  # staging
curl -I https://myeshopstore.online              # production, expect 200
docker logs eshop_web --tail 50                  # app errors
docker exec eshop_db mysql -u root -p -e "SHOW DATABASES;"
```

Then push a commit to `develop` and confirm Jenkins builds, pushes to GHCR, and
redeploys staging end to end.

---

## Troubleshooting

**`Out of host capacity`** — the region has no free ARM capacity. The retry loop in
Phase 1 is the fix; leave it running. If it fails for hours, subscribe to another
region (Ashburn, Frankfurt, London are larger) and rerun Phase 1 there. Networking
resources do not carry across regions, so the whole phase repeats.

**Site unreachable but containers are running** — almost always the iptables layer.
Check both:
```bash
sudo iptables -L INPUT -n --line-numbers | head -20     # port must be ACCEPTed
```
and confirm the port exists in the VCN Security List in the console. Both must allow it.

**`exec format error` when a container starts** — an x86 image on this ARM host.
Everything in the stack (php:8.3-apache, mysql:8.0, nginx:alpine, certbot,
jenkins:lts-jdk21) publishes arm64 variants, so this only appears if an image was
built elsewhere on x86 and pushed to GHCR. Because Jenkins builds *on* this ARM
server, images it pushes are arm64 — fine for this host, but they will not run on an
x86 machine. To publish both, switch the build stage to
`docker buildx build --platform linux/amd64,linux/arm64 --push`.

**MySQL container restarting** — usually memory. A 1 OCPU / 6 GB instance runs the
prod stack plus Jenkins comfortably, but prod + staging + Jenkins together is tight.
The staging compose file already sets `mem_limit` values; stop the staging stack
(`docker compose -f docker-compose.staging.yml down`) if prod is unstable.

**Lost the SSH key** — OCI cannot re-issue it. Either attach the boot volume to a
new instance to fix `authorized_keys`, or recreate the instance (Phase 1 again; the
VCN and subnet are reusable).

---

## What was lost with the old droplet

The droplet was destroyed without a snapshot, so prod/staging MySQL data, uploaded
product images (`eshop_uploads_data`), Let's Encrypt certs, and the Jenkins home
volume are gone. The new database seeds from `db/init.sql` plus `db/migrations/`,
giving a clean catalogue with the default admin (`admin` / `admin123` — change it on
first login). Certs reissue in Phase 5. Jenkins config is rebuilt in Phase 3.

To avoid a repeat, once this is running:

```bash
# OCI console → Storage → Boot Volumes → eshop-server → Create Backup Policy
# or a quick nightly DB dump:
docker exec eshop_db mysqldump -u root -p"$DB_ROOT_PASS" --all-databases > ~/backup-$(date +%F).sql
```
