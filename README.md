# businessLabels

## Local Elasticsearch (Docker)

This project uses the official Elasticsearch Docker image:

- `docker.elastic.co/elasticsearch/elasticsearch:9.1.4`

Start Elasticsearch locally:

```bash
docker compose -f docker-compose.elastic.local.yml up -d
```

Verify it is ready before running seeders:

```bash
curl -sS http://localhost:9200
```

Then run:

```bash
php artisan migrate:fresh --seed --no-interaction
```

Notes:

- This local setup has `xpack.security.enabled=false`, so `.env` can keep:
	- `ELASTIC_USERNAME=`
	- `ELASTIC_PASSWORD=`
- If Docker just pulled the image for the first time, give Elasticsearch a few seconds to become reachable before seeding.


# Once Change any configs on searchables, run this command:
- php artisan elastic:update

- php artisan scout:import "App\Models\MasterProduct"
- php artisan scout:import "App\Models\Product"
- php artisan scout:import "App\Models\GroupProduct"
