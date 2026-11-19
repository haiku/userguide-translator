VERSION ?= 1.1-3
REGISTRY ?= ghcr.io/haiku
ENGINE ?= podman

default:
	${ENGINE} build --no-cache --tag ${REGISTRY}/userguide:${VERSION} .
test:
	${ENGINE} run -e BASE_DOMAIN=https://i18n-next.haiku-os.org ${REGISTRY}/userguide:${VERSION}
push:
	${ENGINE} push ${REGISTRY}/userguide:${VERSION}
