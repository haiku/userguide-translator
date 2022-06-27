VERSION = 1.0

default:
	docker build --no-cache --tag docker.io/haiku/userguide:$(VERSION) .
test:
	docker run -e BASE_DOMAIN=https://i18n-next.haiku-os.org docker.io/haiku/userguide:$(VERSION)
push:
	docker push docker.io/haiku/userguide:$(VERSION)
