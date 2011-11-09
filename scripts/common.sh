

convert_tmpl() {
	FROM=$1
	TO=$2
	cat $FROM | perl -pe 's/\$\{(.*?)\}/$ENV{$1}/e' >$TO
}

guess_src_root() {
	CUR=$(pwd)
	while [ ! -d "$CUR/.git" ] ; do
		CUR=$(dirname $CUR)
		if [ $CUR == "/" ] ; then
			echo "failed guess source root. No suggestions. sorry"
			exit 1;
		fi
	done
	echo $CUR
}

get_version() {
	dpkg-query -W -f='${Version}\n' $1
}

version_ge() {
	dpkg --compare-versions $1 '>=' $2
}

# Common variables
export SRC_ROOT=$(guess_src_root)
export CONF_DIR=$SRC_ROOT/configs

export LOGS_DIR=$SRC_ROOT/logs
test ! -d $LOGS_DIR && mkdir $LOGS_DIR

export TMP_DIR=$SRC_ROOT/tmp
test ! -d $TMP_DIR && mkdir $TMP_DIR

# Developer ports
export NGINX_PORT=2000
export HTTPD_PORT=2100
export HUB_PORT=2200

# Database
export DATABASE=mapcraft-devel
export DB_USER=develcrafter
export DB_PASS=develaccess
export DB_HOST=localhost

# Auth
export AUTH_TYPE="fake"


# Configs
export HUB_CONFIG=$TMP_DIR/hub.config
