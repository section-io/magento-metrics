#!/bin/bash -ex

pushd /workspace >>/dev/null

rm --force "sectionio-magento-metrics-${EXTENSION_VERSION}.zip"
zip -r "sectionio-magento-metrics-${EXTENSION_VERSION}.zip" . -x '.*' -x 'Dockerfile' -x 'build.sh' -x '.editorconfig' -x 'copy-identifiers.txt'

popd >>/dev/null

#php /phpcs.phar --standard=MEQP2 /workspace
