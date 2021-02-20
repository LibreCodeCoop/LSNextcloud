# This file is licensed under the Affero General Public License version 3 or
# later. See the LICENSE file.

# Dependencies:
# * make
# * zip: for building the archive

app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
build_directory=$(CURDIR)/build/artifacts/
package_name=$(build_directory)/$(app_name)
composer=$(shell which composer 2> /dev/null)

all: clean composer
release: composer pack

# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (,$(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

# Cleaning
.PHONY: clean
clean:
	rm -rf vendor/
	rm -rf build

.PHONY: pack
pack:
	rm -rf $(build_directory)
	mkdir -p $(build_directory)
	composer install --no-dev
	zip $(package_name).zip \
	-x '/.git/*' \
	-x '/composer.*' \
	-x '/.gitignore' \
	-x '/Makefile' \
	-r .