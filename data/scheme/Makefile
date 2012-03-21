DB_NAME=mapcraft-devel

all:
	@echo "Usage:\n   make update"

update:
	pg_dump -s ${DB_NAME} >scheme.sql
