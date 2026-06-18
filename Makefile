REGISTRY := 192.168.1.50:10140
IMAGE := matches-api-laravel
TAG := latest

.PHONY: docker-builder docker-build

docker-builder:
	docker buildx create --config ~/.docker/buildkitd.toml --name multiarch_builder --driver docker-container --bootstrap --use

docker-build:
	docker buildx build --platform linux/amd64,linux/arm64 \
		--tag $(REGISTRY)/$(IMAGE):$(TAG) \
		. --push
