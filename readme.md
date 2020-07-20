# Orba Sample Data
 
Module adds command to dump media product images with some limitations:

* Selective products SKUs
* Total max products images size

It allows you to make convenient-size data snapshots for project/developers/CICD processes.
You can filter only needed products including various products types or limit total images weight to given size.

## Install

```
composer require orba/module-sample-data
bin/magento setup:upgrade
```

## Usage Media

```
bin/magento orba:sampledata:media [--skus=SKU1,SKU2,...] [--max-size=N] [--maintenance]
```
* `--skus=SKU1,SKU2,...` - Optional, filter comma separated SKUs
* `--max-size=N` - Optional, allowes to limit aggregated size of product images to N (in MB).
* `--maintenance` - Optional, enable maintenance mode

Result tgz file will be placed in `var/backups` directory.

#### Only product selection 

Complex product children will be attached into the dump too.

```
bin/magento orba:sampledata:media --skus=WJ10,WJ11,WJ12
```

#### Limit product images size

In this case product images will be limited to 20MB.

```
bin/magento orba:sampledata:media --max-size=20
```

#### Mixed filters

```
bin/magento orba:sampledata:media --skus=WJ10,WJ11,WJ12 --max-size=20
```

#### Full dump
```
bin/magento orba:sampledata:media
```

## Usage Anonymize

Anonymizes database based on yaml configuration

```
orba:sampledata:db-anonymize
``` 

#### Configuration
Base yaml configuration is in

```
magento/vendor/orba/module-sample-data/etc/anonymize
```

You can extend this configuration by adding your own yaml files in:

```
magento/app/etc/anonymize/
```

### Modules used

Extension https://github.com/elgentos/masquerade to anonymize data

 

