#!/usr/bin/env bash
# scripts/oci-provision.sh
# Provisions the whole OCI side in one run, from an OCI Cloud Shell session:
#   VCN → internet gateway → route → security list → public subnet → SSH key
#   → Ubuntu 22.04 ARM image lookup → instance launch (retrying on capacity)
#
# Safe to re-run: existing resources are reused, not duplicated. If the launch
# fails with "Out of host capacity" it keeps retrying, rotating through every
# availability domain in the region.
#
# Usage (in OCI Cloud Shell — NOT on your laptop):
#   bash oci-provision.sh
#
# Stop a retry loop with Ctrl+C; re-running picks up where it left off.

set -euo pipefail

NAME="${NAME:-eshop}"
OCPUS="${OCPUS:-1}"
MEMORY_GB="${MEMORY_GB:-6}"
KEY_PATH="${KEY_PATH:-$HOME/${NAME}_key}"
# 5 minutes, not 30s: Oracle rate-limits launch_instance per user and answers a
# hammered endpoint with 429 TooManyRequests, which buys nothing.
RETRY_SECONDS="${RETRY_SECONDS:-300}"
MAX_BACKOFF="${MAX_BACKOFF:-3600}"

log() { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }
ok()  { printf '  \033[0;32m✓\033[0m %s\n' "$*"; }

# ── Identity ──────────────────────────────────────────────────
log "Reading tenancy and availability domains"
COMPARTMENT_ID=$(oci iam availability-domain list --query 'data[0]."compartment-id"' --raw-output)
mapfile -t ADS < <(oci iam availability-domain list --query 'data[*].name' --raw-output | tr -d '[]",' | tr ' ' '\n' | grep -v '^$')
ok "Compartment: ${COMPARTMENT_ID:0:40}…"
ok "Availability domains: ${#ADS[@]} (${ADS[*]})"

# ── VCN ───────────────────────────────────────────────────────
log "Virtual cloud network"
VCN_ID=$(oci network vcn list --compartment-id "$COMPARTMENT_ID" --display-name "$NAME-vcn" \
          --query 'data[0].id' --raw-output 2>/dev/null || true)
if [[ -z "$VCN_ID" || "$VCN_ID" == "null" ]]; then
  VCN_ID=$(oci network vcn create --compartment-id "$COMPARTMENT_ID" --cidr-block 10.0.0.0/16 \
            --display-name "$NAME-vcn" --dns-label "${NAME}vcn" \
            --wait-for-state AVAILABLE --query 'data.id' --raw-output)
  ok "Created $NAME-vcn"
else
  ok "Reusing existing $NAME-vcn"
fi

# ── Internet gateway ──────────────────────────────────────────
log "Internet gateway"
IGW_ID=$(oci network internet-gateway list --compartment-id "$COMPARTMENT_ID" --vcn-id "$VCN_ID" \
          --query 'data[0].id' --raw-output 2>/dev/null || true)
if [[ -z "$IGW_ID" || "$IGW_ID" == "null" ]]; then
  IGW_ID=$(oci network internet-gateway create --compartment-id "$COMPARTMENT_ID" --vcn-id "$VCN_ID" \
            --is-enabled true --display-name "$NAME-igw" \
            --wait-for-state AVAILABLE --query 'data.id' --raw-output)
  ok "Created $NAME-igw"
else
  ok "Reusing existing gateway"
fi

# ── Route table: default route to the internet ────────────────
log "Route table"
RT_ID=$(oci network vcn get --vcn-id "$VCN_ID" --query 'data."default-route-table-id"' --raw-output)
oci network route-table update --rt-id "$RT_ID" \
  --route-rules '[{"destination":"0.0.0.0/0","destinationType":"CIDR_BLOCK","networkEntityId":"'"$IGW_ID"'"}]' \
  --force >/dev/null
ok "0.0.0.0/0 → internet gateway"

# ── Security list: open the ports the stack needs ─────────────
log "Security list (22 SSH, 80/443 web, 8080 staging, 8090 Jenkins)"
SL_ID=$(oci network vcn get --vcn-id "$VCN_ID" --query 'data."default-security-list-id"' --raw-output)
RULES=""
for port in 22 80 443 8080 8090; do
  [[ -n "$RULES" ]] && RULES+=","
  RULES+='{"protocol":"6","source":"0.0.0.0/0","sourceType":"CIDR_BLOCK","isStateless":false,'
  RULES+='"tcpOptions":{"destinationPortRange":{"min":'"$port"',"max":'"$port"'}}}'
done
printf '[%s]' "$RULES" > /tmp/${NAME}-ingress.json
oci network security-list update --security-list-id "$SL_ID" \
  --ingress-security-rules "file:///tmp/${NAME}-ingress.json" --force >/dev/null
ok "Ingress rules applied"

# ── Public subnet ─────────────────────────────────────────────
log "Public subnet"
SUBNET_ID=$(oci network subnet list --compartment-id "$COMPARTMENT_ID" --vcn-id "$VCN_ID" \
             --display-name "$NAME-public-subnet" --query 'data[0].id' --raw-output 2>/dev/null || true)
if [[ -z "$SUBNET_ID" || "$SUBNET_ID" == "null" ]]; then
  SUBNET_ID=$(oci network subnet create --compartment-id "$COMPARTMENT_ID" --vcn-id "$VCN_ID" \
               --cidr-block 10.0.1.0/24 --display-name "$NAME-public-subnet" \
               --route-table-id "$RT_ID" --security-list-ids '["'"$SL_ID"'"]' \
               --prohibit-public-ip-on-vnic false \
               --wait-for-state AVAILABLE --query 'data.id' --raw-output)
  ok "Created $NAME-public-subnet"
else
  ok "Reusing existing subnet"
fi

# ── SSH key ───────────────────────────────────────────────────
log "SSH key pair"
if [[ -f "$KEY_PATH" ]]; then
  ok "Reusing $KEY_PATH"
else
  ssh-keygen -t rsa -b 2048 -f "$KEY_PATH" -N "" >/dev/null
  ok "Generated $KEY_PATH"
fi

# ── Image ─────────────────────────────────────────────────────
log "Ubuntu 22.04 ARM image"
IMAGE_ID=$(oci compute image list --compartment-id "$COMPARTMENT_ID" \
            --operating-system "Canonical Ubuntu" --operating-system-version "22.04" \
            --shape "VM.Standard.A1.Flex" --sort-by TIMECREATED --sort-order DESC \
            --query 'data[0].id' --raw-output)
[[ -n "$IMAGE_ID" && "$IMAGE_ID" != "null" ]] || { echo "ERROR: no Ubuntu 22.04 ARM image found" >&2; exit 1; }
ok "${IMAGE_ID:0:40}…"

# ── Already running? ──────────────────────────────────────────
EXISTING=$(oci compute instance list --compartment-id "$COMPARTMENT_ID" --display-name "$NAME-server" \
            --lifecycle-state RUNNING --query 'data[0].id' --raw-output 2>/dev/null || true)

if [[ -n "$EXISTING" && "$EXISTING" != "null" ]]; then
  log "Instance already running — skipping launch"
  INSTANCE_ID="$EXISTING"
else
  # ── Launch, rotating availability domains on capacity errors ──
  log "Launching ${OCPUS} OCPU / ${MEMORY_GB} GB ARM instance"
  echo "  Free ARM capacity is scarce; this retries every ${RETRY_SECONDS}s across all ADs."
  echo "  Leave it running — Ctrl+C to stop."
  attempt=0
  wait_for=$RETRY_SECONDS
  while true; do
    for AD in "${ADS[@]}"; do
      attempt=$((attempt + 1))
      printf '  [%s] attempt %d — %s … ' "$(date +%H:%M:%S)" "$attempt" "$AD"
      if INSTANCE_ID=$(oci compute instance launch \
            --compartment-id "$COMPARTMENT_ID" \
            --availability-domain "$AD" \
            --shape "VM.Standard.A1.Flex" \
            --shape-config '{"ocpus":'"$OCPUS"',"memoryInGBs":'"$MEMORY_GB"'}' \
            --display-name "$NAME-server" \
            --image-id "$IMAGE_ID" \
            --subnet-id "$SUBNET_ID" \
            --assign-public-ip true \
            --ssh-authorized-keys-file "${KEY_PATH}.pub" \
            --wait-for-state RUNNING \
            --query 'data.id' --raw-output 2>/tmp/${NAME}-launch.err); then
        printf '\033[0;32mSUCCESS\033[0m\n'
        break 2
      fi
      if grep -q "Out of host capacity" /tmp/${NAME}-launch.err; then
        printf 'no capacity\n'
        wait_for=$RETRY_SECONDS
      elif grep -qE "TooManyRequests|429" /tmp/${NAME}-launch.err; then
        # Being throttled; backing off is the only thing that helps.
        wait_for=$(( wait_for * 2 ))
        # 'if', not '&& ' — a false (( )) returns 1 and set -e would abort here.
        if (( wait_for > MAX_BACKOFF )); then wait_for=$MAX_BACKOFF; fi
        printf 'rate limited (429), backing off to %ss\n' "$wait_for"
      else
        printf '\033[0;31mfailed\033[0m\n'
        cat /tmp/${NAME}-launch.err >&2
        exit 1
      fi
    done
    echo "  sleeping ${wait_for}s before next attempt"
    sleep "$wait_for"
  done
fi

# ── Public IP ─────────────────────────────────────────────────
log "Fetching public IP"
PUBLIC_IP=$(oci compute instance list-vnics --instance-id "$INSTANCE_ID" \
             --query 'data[0]."public-ip"' --raw-output)

cat <<EOF

======================================================
 Instance is up.

   Public IP : $PUBLIC_IP
   SSH       : ssh -i $KEY_PATH ubuntu@$PUBLIC_IP

 NEXT STEPS

 1. Save the private key off Cloud Shell (storage here is not a backup):
      cat $KEY_PATH
    Copy the output into a local file and chmod 600 it.

 2. SSH in and bootstrap the server:
      ssh -i $KEY_PATH ubuntu@$PUBLIC_IP
      curl -fsSL https://raw.githubusercontent.com/geethashankar1/onlinestore/main/scripts/setup-server-oci.sh | sudo bash

 3. Put $PUBLIC_IP into the Jenkinsfile as SERVER_IP, commit, push.

 Full runbook: DEPLOY_OCI.md
======================================================
EOF
