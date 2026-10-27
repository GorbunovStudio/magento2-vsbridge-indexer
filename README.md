# Divante VueStorefrontIndexer Extension for Magento2
![Branch stable](https://img.shields.io/badge/stable%20branch-master-blue.svg)
![Branch Develop](https://img.shields.io/badge/dev%20branch-develop-blue.svg)
<a href="https://join.slack.com/t/vuestorefront/shared_invite/enQtMzA4MTM2NTE5NjM2LTI1M2RmOWIyOTk0MzFlMDU3YzJlYzcyYzNiNjUyZWJiMTZjZjc3MjRlYmE5ZWQ1YWRhNTQyM2ZjN2ZkMzZlNTg">![Branch Develop](https://img.shields.io/badge/community%20chat-slack-FF1493.svg)</a>

This projects is a native, Magento2 data indexer for [Vue Storefront - first Progressive Web App for e-Commerce](https://github.com/DivanteLtd/vue-storefront). It fills the OpenSearch data index with all the products, categories and static information required by Vue Storefront to work.

**Note on mage2vuestorefront project**: This native indexer updates the OpenSearch index in the very same format like the [mage2vuestorefront](https://github.com/DivanteLtd/mage2vuestorefront). Our intention was to speed up the indexation process and make it more reliable. With native indexer we were able to use the Magento2 ORM and events to optimize the indexation process. Please do use this module instead of mage2vuestorefront if You experience any issues regarding indexing performance. Both projects are currently supported.

Vue Storefront is a standalone PWA storefront for your eCommerce, possible to connect with any eCommerce backend (eg. Magento, Pimcore, Prestashop or Shopware) through the API.

 ## Video demo
 [![See how it works!](https://github.com/DivanteLtd/vue-storefront/raw/master/docs/.vuepress/public/Fil-Rakowski-VS-Demo-Youtube.png)](https://www.youtube.com/watch?v=L4K-mq9JoaQ)
Sign up for a demo at https://vuestorefront.io/ (Vue Storefront integrated with Pimcore OR Magento2).

## Overview



### Version 1.x 
Pull Requests should be made against 1.x branch. Changes from this branch won't be merge to main branch.
Only fixes will be accepted.

### Version 2.0
Supports OpenSearch 2.x with one index per entity and store.

##### To read what changed click [here](CHANGELOG.MD#20)
 
##### How to upgrade to 2.0
Click here to find out [more](docs/upgrade-to-2.0.md)

##### Version 1.5.0/1.5.1 - support for aliases.
Command ` php bin/magento vsbridge:reindex --all` will reindex all data to new index.
It will create new index and update aliases at the end.

If you used previous versions, you will have to delete index (created with this extension) from ES manually:
If you won't do that, you will get error when running
`"error":{"root_cause":[{"type":"invalid_alias_name_exception","reason":"Invalid alias name [vue_storefront_magento_1], an index exists with the same name as the alias"`

## Installation/Getting Started

- Install with composer
```json
composer require divante/magento2-vsbridge-indexer
```

```php
php bin/magento setup:upgrade
```

## Installation/Getting Started - MSI support
- Install second module which will support MSI
```json
composer require divante/magento2-vsbridge-indexer-msi
```
Not fully supported, few fields are exported to ES.
From inventory indexer:
 -- qty => qty, 
 -- is_salable => is_in_stock/stock_status
 
#### Example

Website 1
```json
{
    "sku": "24-MB01",
    "stock": {
      "qty": 100,
      "is_in_stock": false,
      "stock_status": 0
    }
}
```

Website 2
```json
{
    "sku": "24-MB01",
    "stock": {
      "qty": 73,
      "is_in_stock": true,
      "stock_status": 1
    }
}
```

```php
php bin/magento setup:upgrade
```

### Magento Configuration
Configure the module in Magento panel and run full indexation.

**Check configuration [here](docs/configuration.md)** 

### OpenSearch connection and aliases

This version targets OpenSearch 2.x using `opensearch-project/opensearch-php:2.4.5`. Elasticsearch support and the engine version selector have been removed. Existing connection configuration paths are preserved.

Each entity and store has its own alias, for example `vue_storefront_catalog_1_product` and `vue_storefront_catalog_1_category`. Physical indexes are built separately and published through these aliases. Configure the consuming VSF API to use typeless requests and the corresponding entity aliases.

Read the [OpenSearch migration guide](docs/opensearch-migration.md) before upgrading an existing installation.

### Running the full indexation:
There are two options to run full indexations

1. Indexation of new indexes. 

In general, this indexation can be run in any order. It is worth, to begin with taxrule as it is the fastest.
```php
php bin/magento indexer:reindex vsbridge_taxrule_indexer
php bin/magento indexer:reindex vsbridge_attribute_indexer
php bin/magento indexer:reindex vsbridge_product_indexer
php bin/magento indexer:reindex vsbridge_category_indexer
php bin/magento indexer:reindex vsbridge_cms_block_indexer
php bin/magento indexer:reindex vsbridge_cms_page_indexer
php bin/magento indexer:reindex vsbridge_review_indexer
```


2. Reindexation of all indexes

Recommended for smaller databases. In the case of big databases it is better to run commands manually. 
```php
php bin/magento indexer:reindex
```

or
```php
php bin/magento vsbridge:reindex --store=[STORE ID|STORE CODE]
php bin/magento vsbridge:reindex --store=1
```

Note: If a docker with OpenSearch is disabled, Indexer will display error: "No alive nodes found in your cluster".

### Single Entity Indexing:
You can use single entity indexing while debugging or triggering an update for the index for a single entity.
```php
php bin/magento vsbridge:index <index> <storeId> <entityId>
```
You must provide an index-name, a store ID and an entity ID.
If you need a list off all available index-names, you can use `php bin/magento indexer:status`. All the relevant indexes are prefixed with `vsbridge_`

#### Update on Save Mode

*Update on Save* mode works for the following operations:

- save/delete the product
- save/delete the category 
- save/delete the static block 
- save/delete the static page 
- save/delete the attribute (deleting the attribute causes displaying “invalid” status for vsbridge products indexer).
- save/delete the review

#### Update on Schedule Mode

*Update on Schedule* mode observes changes in corresponding tables, and probably will be more relevant in most cases. It is the default mode in any bigger stores.
     
     
     
### Compatibility

- OpenSearch server: 2.x target; integration checks run against 2.15.0.
- OpenSearch PHP client: 2.4.5; PHP 8.1+ required, subject to Magento's stricter PHP requirements.
- Dependency resolution/platform checks: PHP 8.5.6 with Magento Framework 103.0.9. This is not certification of a complete Magento installation or of every historical Magento version.
- Storefront compatibility must be checked separately. Older VSF API versions may send mapping types that OpenSearch rejects.

See [validation details and migration steps](docs/opensearch-migration.md).

### TODO
- add a limitation of the categories sent to OpenSearch, by adding new configurations: send only categories visible in the menu, send only active categories @Agata
- add a new command allowing to enable/disable following indexes: CMS Block, CMS Page.
- add an option to exclude the default Magento indexes (which do not impact new indexes operations)
- split catalog module to smaller modules
- remove "vsbridge_product_category" all together and run partial update in ES in different way
