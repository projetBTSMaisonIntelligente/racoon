#! /bin/bash

# Installation dépendances
cd ${1}
curl -sS https://getcomposer.org/installer | php
php composer.phar require wensleydale/spark:dev-master
