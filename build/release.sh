#/bin/bash

NEXT_VERSION=$1
CURRENT_VERSION=$(cat composer.json | grep version | head -1 | awk -F= "{ print $2 }" | sed 's/[version:,\",]//g' | tr -d '[[:space:]]')

sed -ie "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEXT_VERSION\"/g" composer.json
rm -rf composer.jsone

sed -ie "s/Version:           $CURRENT_VERSION/Version:           $NEXT_VERSION/g" lexo-captcha.php
rm -rf lexo-captcha.phpe

sed -ie "s/Stable tag: $CURRENT_VERSION/Stable tag: $NEXT_VERSION/g" readme.txt
rm -rf readme.txte

sed -ie "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEXT_VERSION\"/g" info.json
rm -rf info.jsone

sed -ie "s/v$CURRENT_VERSION/v$NEXT_VERSION/g" info.json
rm -rf info.jsone

sed -ie "s/$CURRENT_VERSION.zip/$NEXT_VERSION.zip/g" info.json
rm -rf info.jsone

npx mix --production
sudo composer dump-autoload -oa

mkdir lexo-captcha

cp -r assets lexo-captcha
cp -r languages lexo-captcha
cp -r dist lexo-captcha
cp -r src lexo-captcha
cp -r vendor lexo-captcha
cp -r functions lexo-captcha

cp ./*.php lexo-captcha
cp LICENSE lexo-captcha
cp readme.txt lexo-captcha
cp README.md lexo-captcha
cp CHANGELOG.md lexo-captcha

zip -r ./build/lexo-captcha-$NEXT_VERSION.zip lexo-captcha -q
