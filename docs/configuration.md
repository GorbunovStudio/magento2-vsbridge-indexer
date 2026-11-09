### Magento Configuration
Go to the new ‘Indexer’ section (Stores → Configuration → Vuestorefront → Indexer), available now in the in the Magento Panel, and configure it in the listed areas:
1. ####General settings 

   **Enable VS Bridge** → Enable to export data to OpenSearch. By default indexing is disable.

    OpenSearch 2.x is the only backend. The old engine selector has been removed; persisted `es_version` values are ignored.

    **List of stores to reindex** → Select stores for which data must be exported to OpenSearch. By default store with ID 1 is selected. For each store view, a new, separate OpenSearch index is created.

    ![](./images/config-general.png)

1. ####OpenSearch Client

   Configure connection with OpenSearch. Provide a host, port, and set up login and password (optionally).

   ![](./images/config-es.png)

1. ####Indicies settings
 
   **Batch Indexing Size** → select size of packages by which you intend to send data to OpenSearch. Depending on the project you might need to adjust package size to the number of products, attributes, configurable products variation, etc). By default Batch, Indexing Size is set up for 1000.
   Indicies settings
    
   **Index Alias Prefix** → prefix for all entity aliases, default `vue_storefront_catalog`.

   **Index Identifier** → append the store ID (default) or store code before the entity name. Examples: `vue_storefront_catalog_1_product`, `vue_storefront_catalog_en_category`.

   **Add Index Identifier to Default Store View** → when disabled, omit the store identifier only for the default store, for example `vue_storefront_catalog_product`.

   Each entity/store alias points to its current physical index. Existing entity-specific naming is preserved. Update the consuming VSF API to use these aliases and typeless requests; see the [migration guide](opensearch-migration.md). Older screenshots may show the removed Elasticsearch version selector.

1. ####Redis Cache Settings

    **Clear cache** → Yes/No (by default this option is disabled)
    
    **VSF base Url** → URL for VSF
 
    **Invalidate Secret cache key** → provide the same value as in the VSF configuration
 
    **Connection timeout** → by default set up for 10 seconds
    
    ![](./images/config-cache.png) 

1. ####Optimization Settings

    **Check cluster health** → disabled by default. When enabled, only green cluster status permits indexing. A single-node test cluster with replicas may be yellow.
    
    **Check max bulk queue** → unsupported with OpenSearch. Leave disabled. If an existing installation has this enabled, indexing reports an actionable error until it is disabled. The old heuristic does not measure bulk write capacity.
    
    **Change number of replicas** → by default this option is disabled. Used to set number_of_replicas=0 during indexing.
    
    **Default number of replicas** → number of replicas which is set after indexing.
    
    **Change refresh interval** → by default this option is disabled. Used to set refresh_interval=-1 during indexing.
    
    **Default value of refresh interval** → value of refresh interval which is set after indexing.
    
    ![](./images/optimization-settings.png)

1. ####Catalog Settings
    
    **Use Catalog Url Keys** → by default this option is disabled. Use Magento Url Key attribute for url_key and slug field (for products and categories). Url Keys have to be unique
    
    **Use Magento Url Key and ID to generate slug for VSF** -> by default slug (and url_key) field is generated base on product/category NAME and ID
    
    **Sync Tier Prices** → by default this option is disabled. Used to sync products tier prices. 
    
    **Types of products to index** → by default all product will be exported to OpenSearch. This option allows for selecting certain product types that should be exported.
    
    **Add swatches to `product.configurable_options`** → by default No. Use to export information about swatches directly for a product type. 	 
    
    **Apply catalog price rules in `final_price`**  → by default No. Set option to Yes if you use Catalog Price Rules and have Magento version 2.2.4 (or any previous version).
    
    ![](./images/config-catalog.png)
    
    **Product attributes to export***  → choose product attributes for export.
    By default only part of attributes will be exported, like: description, image, small_image, thumbnail, 
    media_gallery, media_title, meta_description, special_price, special_from_date, special_to_date, tax_class_id.
    
    **Configurable children attributes to export***  → choose product attributes for export.
    For configurable_children attributes used to create configurable products are also always exported 
    plus attributes set in configuration (by default: image, small_image, thumbnail, media_gallery, special_price, special_from_date, special_to_date)
    
    ![](./images/config-attributes.png)

 * Some attributes are always exported: price, sku, name, visibility, status, url_path, url_key
 
 1. #### Category Settings
    **Category attributes to export**  → choose category attributes for export. By Default all attributes are exported.
    
    **Category children data attributes to export**  → choose attributes for export for sub categories. Some attributes will be always exported: name, url_key, is_active, url_path. 

    ![](./images/config-category.png)

After updating the configuration, you can run the indexation.
It is also worth query OpenSearch using CURL, to be sure that the communication works.
