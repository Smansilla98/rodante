up:
	DOCKER_HOST=unix:///var/run/docker.sock docker compose up -d --build

down:
	DOCKER_HOST=unix:///var/run/docker.sock docker compose down

fresh:
	DOCKER_HOST=unix:///var/run/docker.sock docker compose exec app php artisan migrate:fresh --seed --force

test:
	DOCKER_HOST=unix:///var/run/docker.sock docker compose exec app php artisan test

logs:
	DOCKER_HOST=unix:///var/run/docker.sock docker compose logs -f app
