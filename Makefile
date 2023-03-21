VERSION ?= 1.0-1
REGISTRY ?= ghcr.io/haiku

default:
	docker build --no-cache --tag ${REGISTRY}/userguide:${VERSION} .
test:
	docker run -e BASE_DOMAIN=https://i18n-next.haiku-os.org ${REGISTRY}/userguide:${VERSION}
push:
	docker push ${REGISTRY}/userguide:${VERSION}
