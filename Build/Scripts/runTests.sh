#!/usr/bin/env bash

#
# Test runner of EXT:language_limits, based on docker or podman.
# Adapted from the test runner of b13/container and TYPO3 Core.
#
if [ "${CI}" != "true" ]; then
    trap 'echo "runTests.sh SIGINT signal emitted";cleanUp;exit 2' SIGINT
fi

printSummary() {
    cleanUp

    echo "" >&2
    echo "###########################################################################" >&2
    echo "Result of ${TEST_SUITE}" >&2
    echo "Container runtime: ${CONTAINER_BIN}" >&2
    echo "Container suffix: ${SUFFIX}"
    echo "PHP: ${PHP_VERSION}" >&2
    echo "TYPO3: ${TYPO3}" >&2
    if [[ ${TEST_SUITE} =~ ^functional$ ]]; then
        case "${DBMS}" in
            mariadb|mysql|postgres)
                echo "DBMS: ${DBMS}  version ${DBMS_VERSION}  driver ${DATABASE_DRIVER}" >&2
                ;;
            sqlite)
                echo "DBMS: ${DBMS}" >&2
                ;;
        esac
    fi
    if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
        echo "SUCCESS" >&2
    else
        echo "FAILURE" >&2
    fi
    echo "###########################################################################" >&2
    echo "" >&2
    exit ${SUITE_EXIT_CODE}
}

waitFor() {
    local HOST=${1}
    local PORT=${2}
    local TESTCOMMAND="
        COUNT=0;
        while ! nc -z ${HOST} ${PORT}; do
            if [ \"\${COUNT}\" -gt 10 ]; then
              echo \"Can not connect to ${HOST} port ${PORT}. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    [[ -n "$3" ]] && echo -n "Startup wait of $3 ... " && sleep $3 && echo "done"
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_ALPINE} /bin/sh -c "${TESTCOMMAND}"
    if [[ $? -gt 0 ]]; then
        kill -SIGINT -$$
    fi
}

cleanUp() {
    echo "Remove container for network \"${NETWORK}\""
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} kill ${ATTACHED_CONTAINER} >/dev/null
    done
    if [ ${CONTAINER_BIN} = "docker" ]; then
        ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
    else
        ${CONTAINER_BIN} network rm -f ${NETWORK} >/dev/null
    fi
}

handleDbmsOptions() {
    # -a, -d, -i depend on each other. Validate input combinations and set defaults.
    case ${DBMS} in
        mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10.11"
            if ! [[ ${DBMS_VERSION} =~ ^(10.6|10.11|11.4|11.8)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        mysql)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="8.4"
            if ! [[ ${DBMS_VERSION} =~ ^(8.0|8.4|9.0)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        postgres)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="16"
            if ! [[ ${DBMS_VERSION} =~ ^(14|15|16|17)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            if [ -n "${DBMS_VERSION}" ]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        *)
            echo "Invalid option -d ${DBMS}" >&2
            echo >&2
            echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
            exit 1
            ;;
    esac
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
Test runner of EXT:language_limits. Executes unit, functional and other test suites
in a container based test environment.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies the test suite to run
            - cgl: test and fix all PHP files
            - composer: "composer" command dispatcher, to execute various composer commands
            - composerInstall: "composer install" for the TYPO3 version given with -t
            - composerValidate: "composer validate"
            - functional: functional tests
            - lint: PHP linting
            - phpstan: phpstan tests
            - phpstanGenerateBaseline: regenerate the phpstan baseline of the TYPO3 version given with -t
            - unit (default): PHP unit tests

    -b <docker|podman>
        Container environment:
            - podman (default)
            - docker

    -p <8.2|8.3|8.4|8.5>
        Specifies the PHP minor version to be used
            - 8.2 (default): use PHP 8.2
            - 8.3: use PHP 8.3
            - 8.4: use PHP 8.4
            - 8.5: use PHP 8.5 (required for TYPO3 v15)

    -t <14|15>
        Specifies the TYPO3 Core version to be used - Only with -s composerInstall|composerValidate|phpstan|phpstanGenerateBaseline
            - 14 (default): Use TYPO3 v14.3
            - 15: Use TYPO3 v15 (development version)

    -a <mysqli|pdo_mysql>
        Only with -s functional
        Specifies to use another driver, following combinations are available:
            - mysql
                - mysqli (default)
                - pdo_mysql
            - mariadb
                - mysqli (default)
                - pdo_mysql

    -d <sqlite|mariadb|mysql|postgres>
        Only with -s functional
        Specifies on which DBMS tests are performed
            - mariadb (default): use mariadb
            - sqlite: use sqlite
            - mysql: use MySQL
            - postgres: use postgres

    -i version
        Specify a specific database version
        With "-d mariadb":
            - 10.6
            - 10.11 (default)
            - 11.4
            - 11.8
        With "-d mysql":
            - 8.0
            - 8.4 (default)
            - 9.0
        With "-d postgres":
            - 14
            - 15
            - 16 (default)
            - 17

    -x
        Only with -s functional|unit
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -n
        Only with -s cgl
        Activate dry-run in CGL check that does not actively change files and only prints broken ones.

    -u
        Update existing typo3/core-testing-*:latest container images and remove dangling local volumes.

    -h
        Show this help.

Examples:
    # Install dependencies for TYPO3 v14 and run the unit tests with PHP 8.2
    Build/Scripts/runTests.sh -t 14 -s composerInstall
    Build/Scripts/runTests.sh -s unit

    # Run the functional tests on sqlite
    Build/Scripts/runTests.sh -s functional -d sqlite

    # Install dependencies for TYPO3 v15 and run the functional tests with PHP 8.5
    Build/Scripts/runTests.sh -p 8.5 -t 15 -s composerInstall
    Build/Scripts/runTests.sh -p 8.5 -s functional -d sqlite

    # Run a single functional test file with a filter
    Build/Scripts/runTests.sh -s functional -d sqlite Tests/Functional/FrontendRenderingTest.php --filter pages
EOF
}

# Test if docker exists, else exit out with error
if ! type "docker" >/dev/null 2>&1 && ! type "podman" >/dev/null 2>&1; then
    echo "This script relies on docker or podman. Please install" >&2
    exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
ROOT_DIR="${PWD}"

# Option defaults
TEST_SUITE="help"
COMPOSER_ROOT_VERSION="0.1.0-dev"
DBMS="mariadb"
DBMS_VERSION=""
PHP_VERSION="8.2"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
TYPO3="14"
CGLCHECK_DRY_RUN=""
DATABASE_DRIVER=""
CONTAINER_BIN=""
CI_PARAMS="${CI_PARAMS:-}"
CONTAINER_INTERACTIVE="-it --init"
CONTAINER_HOST="host.docker.internal"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
CI_JOB_ID=${CI_JOB_ID:-}
SUFFIX=$(echo $RANDOM)
if [ ${CI_JOB_ID} ]; then
    SUFFIX="${CI_JOB_ID}-${SUFFIX}"
fi
NETWORK="b13-language-limits-${SUFFIX}"

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts "a:b:s:d:i:t:p:xy:nhu" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        b)
            if ! [[ ${OPTARG} =~ ^(docker|podman)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            CONTAINER_BIN=${OPTARG}
            ;;
        a)
            DATABASE_DRIVER=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        i)
            DBMS_VERSION=${OPTARG}
            ;;
        t)
            TYPO3=${OPTARG}
            if ! [[ ${TYPO3} =~ ^(14|15)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.2|8.3|8.4|8.5)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        n)
            CGLCHECK_DRY_RUN="-n"
            ;;
        h)
            TEST_SUITE="help"
            ;;
        u)
            TEST_SUITE=update
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

if [ "${TEST_SUITE}" == "help" ]; then
    loadHelp
    echo "${HELP}"
    exit 0
fi

handleDbmsOptions

if [ "${CI}" == "true" ]; then
    # ENV var "CI" is set by GitHub Actions. Use it to force some CI details.
    CONTAINER_INTERACTIVE=""
elif [ ! -t 0 ] || [ ! -t 1 ]; then
    # No TTY (e.g. a script runner or pipe): drop "-it", but keep "--init" to forward signals.
    CONTAINER_INTERACTIVE="--init"
fi

# determine default container binary to use: 1. podman 2. docker
if [[ -z "${CONTAINER_BIN}" ]]; then
    if type "podman" >/dev/null 2>&1; then
        CONTAINER_BIN="podman"
    elif type "docker" >/dev/null 2>&1; then
        CONTAINER_BIN="docker"
    fi
fi

if [ $(uname) != "Darwin" ] && [ ${CONTAINER_BIN} = "docker" ]; then
    # Run docker jobs as current user to prevent permission issues. Not needed with podman.
    USERSET="--user $HOST_UID"
fi

if ! type ${CONTAINER_BIN} >/dev/null 2>&1; then
    echo "Selected container environment \"${CONTAINER_BIN}\" not found. Please install or use -b option to select one." >&2
    exit 1
fi

IMAGE_PHP="ghcr.io/typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest"
IMAGE_ALPINE="docker.io/alpine:3.8"
IMAGE_MARIADB="docker.io/mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="docker.io/mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="docker.io/postgres:${DBMS_VERSION}-alpine"

# Remove handled options and leaving the rest in the line, so it can be passed raw to commands
shift $((OPTIND - 1))

# Create .cache dir: composer needs this.
mkdir -p .cache
mkdir -p .Build/Web/typo3temp/var/tests

${CONTAINER_BIN} network create ${NETWORK} >/dev/null

if [ ${CONTAINER_BIN} = "docker" ]; then
    # docker needs the add-host for xdebug remote debugging. podman has host.container.internal built in
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network ${NETWORK} --add-host "${CONTAINER_HOST}:host-gateway" ${USERSET} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid,uid=${HOST_UID},gid=${HOST_PID}"
else
    # podman
    CONTAINER_HOST="host.containers.internal"
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm --network ${NETWORK} -v ${ROOT_DIR}:${ROOT_DIR} -w ${ROOT_DIR}"
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid"
fi

if [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=${CONTAINER_HOST}"
fi

# Requires the TYPO3 version given with -t. composer.json is restored afterwards.
COMPOSER_REQUIRE_TYPO3="true"
if [ "${TYPO3}" == "15" ]; then
    COMPOSER_REQUIRE_TYPO3="composer require --no-update --no-interaction typo3/cms-core:^15.0@dev typo3/cms-backend:^15.0@dev typo3/cms-frontend:^15.0@dev"
    COMPOSER_REQUIRE_TYPO3="${COMPOSER_REQUIRE_TYPO3} && composer require --dev --no-update --no-interaction typo3/cms-extbase:^15.0@dev typo3/cms-fluid:^15.0@dev typo3/cms-install:^15.0@dev typo3/testing-framework:dev-main"
fi

# Suite execution
case ${TEST_SUITE} in
    cgl)
        # Active dry-run for cgl needs not "-n" but specific options
        if [ -n "${CGLCHECK_DRY_RUN}" ]; then
            CGLCHECK_DRY_RUN="--dry-run --diff"
        fi
        COMMAND="php -dxdebug.mode=off .Build/bin/php-cs-fixer fix -v ${CGLCHECK_DRY_RUN} --config=Build/php-cs-fixer.php --using-cache=no"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name cgl-${SUFFIX} ${IMAGE_PHP} ${COMMAND}
        SUITE_EXIT_CODE=$?
        ;;
    composer)
        COMMAND=(composer "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    composerInstall)
        rm -rf .Build/vendor .Build/bin composer.lock .Build/Web/typo3conf/ext/
        cp composer.json composer.json.orig
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-install-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "
            php -v | grep '^PHP';
            ${COMPOSER_REQUIRE_TYPO3} &&
            composer update --no-progress --no-interaction
        "
        SUITE_EXIT_CODE=$?
        cp composer.json composer.json.testing
        mv composer.json.orig composer.json
        ;;
    composerValidate)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-validate-${SUFFIX} -e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION} ${IMAGE_PHP} /bin/sh -c "
            php -v | grep '^PHP';
            composer validate --no-check-lock
        "
        SUITE_EXIT_CODE=$?
        ;;
    functional)
        COMMAND=(.Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml --exclude-group not-${DBMS} "$@")
        case ${DBMS} in
            mariadb)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mariadb-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MARIADB} >/dev/null
                waitFor mariadb-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mariadb-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mysql-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MYSQL} >/dev/null
                waitFor mysql-func-${SUFFIX} 3306 2
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mysql-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name postgres-func-${SUFFIX} --network ${NETWORK} -d -e POSTGRES_PASSWORD=funcp -e POSTGRES_USER=funcu --tmpfs /var/lib/postgresql/data:rw,noexec,nosuid ${IMAGE_POSTGRES} >/dev/null
                waitFor postgres-func-${SUFFIX} 5432
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_pgsql -e typo3DatabaseName=bamboo -e typo3DatabaseUsername=funcu -e typo3DatabaseHost=postgres-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # create sqlite tmpfs mount typo3temp/var/tests/functional-sqlite-dbs/ to avoid permission issues
                rm -rf "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs"
                mkdir -p "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/"
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_sqlite --tmpfs ${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/:${TMPFS_MOUNT_OPTIONS}"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
        esac
        ;;
    lint)
        COMMAND='php -v | grep '^PHP'; find . -name \\*.php ! -path "./.Build/\\*" -print0 | xargs -0 -n1 -P4 php -dxdebug.mode=off -l >/dev/null'
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-php-${SUFFIX} ${IMAGE_PHP} bash -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstan)
        COMMAND=(php -dxdebug.mode=off .Build/bin/phpstan analyse -c Build/phpstan${TYPO3}.neon --no-progress --no-interaction --memory-limit 4G "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name phpstan-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    phpstanGenerateBaseline)
        COMMAND=(php -dxdebug.mode=off .Build/bin/phpstan analyse -c Build/phpstan${TYPO3}.neon --no-progress --no-interaction --memory-limit 4G --generate-baseline=Build/phpstan-baseline-${TYPO3}.neon "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name phpstan-baseline-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    unit)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} .Build/bin/phpunit -c Build/phpunit/UnitTests.xml "$@"
        SUITE_EXIT_CODE=$?
        ;;
    update)
        # prune unused, dangling local volumes
        echo "> prune unused, dangling local volumes"
        ${CONTAINER_BIN} volume ls -q -f driver=local -f dangling=true | awk '$0 ~ /^[0-9a-f]{64}$/ { print }' | xargs -I {} ${CONTAINER_BIN} volume rm {}
        echo ""
        # pull typo3/core-testing-*:latest versions of those ones that exist locally
        echo "> pull ghcr.io/typo3/core-testing-*:latest versions of those ones that exist locally"
        ${CONTAINER_BIN} images "ghcr.io/typo3/core-testing-*:latest" --format "{{.Repository}}:latest" | xargs -I {} ${CONTAINER_BIN} pull {}
        echo ""
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        echo "> remove \"dangling\" ghcr.io/typo3/core-testing-* images (those tagged as <none>)"
        ${CONTAINER_BIN} images "ghcr.io/typo3/core-testing-*" --filter "dangling=true" --format "{{.ID}}" | xargs -I {} ${CONTAINER_BIN} rmi -f {}
        echo ""
        SUITE_EXIT_CODE=0
        ;;
    *)
        loadHelp
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
        ;;
esac

# Cleanup, print summary && exit with exitcode
printSummary
