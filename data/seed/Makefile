DB_NAME=mapcraft-devel

all:
	@echo "Usage:\n   make update"

update:
	pg_dump -a ${DB_NAME} >seed.sql
	cp -f ../../static/kml/*.kml kml
	cp -f ../../data/wms/*.map wms

