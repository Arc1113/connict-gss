# Local Network Plan

Status: Phase 10 implemented

The Graduation Stage System runs on a dedicated local ceremony network. Internet
is not required during runtime.

## Recommended topology

```text
Dedicated event router
├── Server laptop   fixed/reserved LAN address
├── Scanner laptop  DHCP reservation or known address
└── Stage laptop    DHCP reservation or known address
```

Example addresses only:

| Device | Example address |
| --- | --- |
| Router | `192.168.50.1` |
| Server | `192.168.50.10` |
| Scanner | `192.168.50.20` |
| Stage | `192.168.50.30` |

Use the actual venue subnet in `.env.production`, firewall rules, printed
operator instructions, and browser bookmarks.

## URLs

If the server is `192.168.50.10`:

| Purpose | URL |
| --- | --- |
| Health | `http://192.168.50.10:8000/up` |
| Login | `http://192.168.50.10:8000/login` |
| Scanner | `http://192.168.50.10:8000/scanner` |
| Stage | `http://192.168.50.10:8000/stage` |
| Stage control | `http://192.168.50.10:8000/stage-control` |
| Admin | `http://192.168.50.10:8000/admin` |
| Reverb | `ws://192.168.50.10:8080` |

## Required ports

- TCP `8000`: Laravel HTTP.
- TCP `8080`: Reverb WebSocket.
- PostgreSQL host maintenance port stays on `127.0.0.1`, not the LAN.

No Vite development port is required.

## Router configuration

Recommended:

- dedicated event router;
- no guest network users;
- DHCP reservation for the server;
- stable Wi-Fi channel or wired links;
- internet uplink disconnected during final runtime test;
- router admin password set before the event;
- SSID and passphrase shared only with operators.

Prefer wired server and stage connections. The scanner laptop may use wired or
stable Wi-Fi.

## Server IP strategy

Preferred order:

1. DHCP reservation on the router for the server laptop MAC address.
2. Static server address configured on the server laptop.
3. Static addressing on all three laptops through an Ethernet switch.

After changing the server address:

1. update `APP_URL`;
2. update `REVERB_ALLOWED_ORIGINS`;
3. rebuild frontend assets if any `VITE_*` value changed;
4. restart app and Reverb;
5. update browser bookmarks.

## Firewall model

The server should allow only the ceremony subnet to reach app and Reverb ports.
PostgreSQL must not be reachable from scanner or stage devices.

Review before applying:

```bash
sudo ufw allow from 192.168.50.0/24 to any port 8000 proto tcp
sudo ufw allow from 192.168.50.0/24 to any port 8080 proto tcp
sudo ufw deny 5432/tcp
sudo ufw deny 54329/tcp
```

## Network fallback order

1. Dedicated router.
2. Replacement preconfigured router.
3. Ethernet switch with static IP addresses.
4. Server laptop hotspot as emergency local network.
5. Phone hotspot only as last-resort local network; internet is unnecessary.

Do not run two active servers with the same identity on the same network.

## Cold standby network switch

To switch to a standby server:

1. Stop the primary server or disconnect it from the ceremony network.
2. Restore the latest verified backup on the standby.
3. Assign the standby the approved server IP or update all browser URLs.
4. Start services on the standby.
5. Run health checks.
6. Refresh scanner, stage-control, and stage browsers.

Never leave primary and standby advertising the same server URL simultaneously.

## Manual verification checklist

- Scanner reaches `/up` and `/scanner` by server LAN IP.
- Stage reaches `/up` and `/stage` by server LAN IP.
- Stage-control reaches `/stage-control` by server LAN IP.
- Reverb connects from scanner/stage network.
- PostgreSQL is not reachable from scanner or stage devices.
- Internet disconnected while LAN workflow still works.
- Router reboot recovery is documented and tested where possible.
