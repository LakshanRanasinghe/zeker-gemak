ELASTIC_COMPOSE_FILE ?= docker-compose.elastic.local.yml
ELASTIC_URL ?= http://localhost:9200
SCOUT_PREFIX ?= business_labels_
CATALOG_INDEXES := catalog_products_simple catalog_products_variable catalog_printers
SCOUT_MODELS := App\\Models\\Product App\\Models\\GroupProduct App\\Models\\MasterProduct App\\Models\\Post
# scout:import builds searchable arrays in batches; the default 128M CLI limit
# is too low for the catalog, so run imports with a raised memory limit.
IMPORT_MEMORY_LIMIT ?= 1G

.PHONY: elastic-up elastic-down elastic-reset elastic-logs elastic-health elastic-indexes elastic-import elastic-flush elastic-reindex elastic-reindex-docker

elastic-up:
	docker compose -f $(ELASTIC_COMPOSE_FILE) up -d
	$(MAKE) elastic-health

elastic-down:
	docker compose -f $(ELASTIC_COMPOSE_FILE) down

elastic-reset:
	docker compose -f $(ELASTIC_COMPOSE_FILE) down -v

elastic-logs:
	docker compose -f $(ELASTIC_COMPOSE_FILE) logs -f elasticsearch

elastic-health:
	@printf "Waiting for Elasticsearch at $(ELASTIC_URL)"
	@for i in $$(seq 1 60); do \
		if curl -fsS $(ELASTIC_URL) >/dev/null 2>&1; then \
			printf "\n"; \
			curl -fsS $(ELASTIC_URL); \
			exit 0; \
		fi; \
		printf "."; \
		sleep 2; \
	done; \
	printf "\nElasticsearch did not become ready in time.\n"; \
	exit 1

elastic-indexes:
	@for index in $(CATALOG_INDEXES); do \
		full_index="$(SCOUT_PREFIX)$$index"; \
		if curl -fsSI $(ELASTIC_URL)/$$full_index >/dev/null 2>&1; then \
			echo "Index $$full_index already exists; skipping create."; \
		else \
			php artisan scout:index "$$full_index"; \
		fi; \
	done

elastic-import:
	$(MAKE) elastic-indexes
	@for model in $(SCOUT_MODELS); do \
		php -d memory_limit=$(IMPORT_MEMORY_LIMIT) artisan scout:import "$$model"; \
	done

elastic-flush:
	@for index in $(CATALOG_INDEXES); do \
		full_index="$(SCOUT_PREFIX)$$index"; \
		if curl -fsSI $(ELASTIC_URL)/$$full_index >/dev/null 2>&1; then \
			php artisan scout:delete-index "$$full_index"; \
		else \
			echo "Skipping $$full_index delete; index does not exist yet."; \
		fi; \
	done

elastic-reindex: elastic-health
	$(MAKE) elastic-flush
	$(MAKE) elastic-import

elastic-reindex-docker: elastic-up elastic-reindex
