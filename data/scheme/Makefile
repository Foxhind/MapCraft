
DB_NAME=mapcraft
DB_USER=postgres


all:
	@echo "Usage:\n   make <install|update>"


install:
	sudo -u ${DB_USER} psql -d ${DB_NAME} <scheme.sql

update:
	sudo -u ${DB_USER} pg_dump -s ${DB_NAME} >scheme.sql

console:
	sudo -u ${DB_USER} psql -d ${DB_NAME}