<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Magento generates these at installation time. Unit tests only need mockable create methods.
foreach ([
    'Api\\BulkRequestInterfaceFactory',
    'Api\\BulkResponseInterfaceFactory',
    'Api\\IndexInterfaceFactory',
    'Indexer\\GenericIndexerHandlerFactory',
] as $factory) {
    $class = 'Divante\\VsbridgeIndexerCore\\' . $factory;
    if (!class_exists($class)) {
        $separator = strrpos($class, '\\');
        eval('namespace ' . substr($class, 0, $separator) . '; class ' . substr($class, $separator + 1)
            . ' { public function create(array $data = []) {} }');
    }
}

foreach ([
    'Magento\\Framework\\HTTP\\Adapter\\CurlFactory',
    'Magento\\Indexer\\Model\\Indexer\\CollectionFactory',
] as $class) {
    if (!class_exists($class)) {
        $separator = strrpos($class, '\\');
        eval('namespace ' . substr($class, 0, $separator) . '; class ' . substr($class, $separator + 1)
            . ' { public function create(array $data = []) {} }');
    }
}
