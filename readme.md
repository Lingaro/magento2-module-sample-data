# Lingaro Sample Data
 
Module adds command to dump media product images with some limitations:

* Selective products SKUs
* Total max products images size

It allows you to make convenient-size data snapshots for project/developers/CICD processes.
You can filter only needed products including various products types or limit total images weight to given size.

## Install

```
composer require lingaro/module-sample-data
bin/magento setup:upgrade
```

## Usage

```
bin/magento lingaro:sampledata:media [--skus=SKU1,SKU2,...] [--max-size=N] [--maintenance]
```
* `--skus=SKU1,SKU2,...` - Optional, filter comma separated SKUs
* `--max-size=N` - Optional, allowes to limit aggregated size of product images to N (in MB).
* `--maintenance` - Optional, enable maintenance mode

### Only product selection 

Complex product children will be attached into the dump too.

```
bin/magento lingaro:sampledata:media --skus=WJ10,WJ11,WJ12
```

### Limit product images size

In this case product images will be limited to 20MB

```
bin/magento lingaro:sampledata:media --max-size=20
```

### Mixed filters

```
bin/magento lingaro:sampledata:media --skus=WJ10,WJ11,WJ12 --max-size=20
```

### Full dump
```
bin/magento lingaro:sampledata:media
```
 
