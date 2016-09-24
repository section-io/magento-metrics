#!/bin/bash -ex

pushd /workspace >>/dev/null

rm --force "sectionio-magento-metrics-${EXTENSION_VERSION}.zip"
zip -r "sectionio-magento-metrics-${EXTENSION_VERSION}.zip" . -x '.git/*'

popd >>/dev/null

php /phpcs.phar --standard=MEQP2 /workspace
