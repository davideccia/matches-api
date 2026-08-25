.PHONY: dev

dev:
	npx concurrently -n "sail,queue,reverb" -c "red,green,blue" "vendor/bin/sail up" "vendor/bin/sail artisan queue:work" "vendor/bin/sail artisan reverb:start"
