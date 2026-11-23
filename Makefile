# Rangkui — perintah bantu pengembangan
# Pemakaian: make <target>

PORT ?= 8080
BRANCH_NAME := $(shell git rev-parse --abbrev-ref HEAD 2>/dev/null)

.PHONY: help install serve test lint check clear release print-branch pull push

help: ## Tampilkan daftar perintah
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

install: ## Pasang dependensi Composer
	composer install

serve: ## Jalankan server pengembangan (http://localhost:$(PORT))
	php spark serve --port $(PORT)

test: ## Jalankan PHPUnit
	vendor/bin/phpunit

lint: ## Periksa sintaks semua berkas PHP aplikasi
	@find app tests -name '*.php' -print0 | xargs -0 -n1 -P4 php -l | grep -v '^No syntax errors' || true

check: lint test ## Lint + tes

clear: ## Bersihkan cache aplikasi
	php spark cache:clear

release: ## Bangun zip rilis (dengan vendor/) ke dist/
	bash .github/scripts/build-release.sh $(shell git describe --tags --always)

print-branch:
	@echo "Current branch is: $(BRANCH_NAME)"

pull:
	git pull origin $(BRANCH_NAME)

push:
	git push origin $(BRANCH_NAME)
