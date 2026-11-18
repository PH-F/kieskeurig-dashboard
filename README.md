# Kieskeurig Dashboard — WordPress Plugin

A WordPress plugin that pulls affiliate data from the [Kieskeurig Partner API](https://partnerprogramma.kieskeurig.nl/) and displays clicks, commissions and transactions in a clean wp-admin dashboard.

## Features

- **Clicks per day** — bar chart of daily click volume (from Kieskeurig's `/clicks/daily` endpoint)
- **Commission per day** — line chart of approved commissions per day
- **Summary per shop** — table with orders, revenue, commission, and status breakdown per webshop
- **Transactions table** — paginated list of all individual transactions with status badges
- **Auto-sync** — WP-Cron syncs transactions every 15 minutes; clicks sync daily
- **Manual sync** — trigger a sync directly from the settings page
- **Date filter** — defaults to the last 90 days; fully adjustable

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A Kieskeurig Partner API key (`wl_xxxx_...`)

## Installation

1. Upload the `kieskeurig-dashboard` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. Go to **Kieskeurig → Instellingen** and enter your API key
4. Click **Nu synchroniseren** to do the first data import

## Configuration

| Setting | Description |
|---|---|
| API Sleutel | Your Kieskeurig Partner API key |
| Auto-sync | Every 15 minutes (transactions) / daily (clicks) via WP-Cron |

## Database tables

The plugin creates two custom tables on activation:

| Table | Contents |
|---|---|
| `wp_kd_transactions` | Individual affiliate transactions (id, shop, amount, commission, status, date) |
| `wp_kd_clicks` | Daily click counts and CPC commissions per shop |

## Directory structure

```
kieskeurig-dashboard/
├── assets/
│   └── admin.css
├── includes/
│   ├── class-kd-admin.php      # Dashboard UI + Chart.js graphs
│   ├── class-kd-api.php        # Kieskeurig API client
│   ├── class-kd-cron.php       # WP-Cron sync jobs
│   └── class-kd-db.php         # DB schema + query methods
└── kieskeurig-dashboard.php    # Plugin bootstrap
```

## API endpoints used

- `GET /transactions` — affiliate transactions (paginated, with `updated_since` cursor)
- `GET /clicks/daily` — click counts and CPC commissions per day per shop

## Transaction statuses

| Status | Meaning |
|---|---|
| `approved` | Commission confirmed and will be paid out |
| `pending` | Transaction received, awaiting review by Kieskeurig |
| `rejected` | Transaction rejected (return, cancellation, etc.) |

## Development

Built with:
- [Chart.js 4.4.0](https://www.chartjs.org/) — graphs
- WordPress `$wpdb` + `dbDelta` — database management
- WP-Cron — scheduled syncs

## License

MIT — developed by [PH-F](https://ph-f.nl) for [FWD.nl](https://fwd.nl).
