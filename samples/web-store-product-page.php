<?php
    public function product() {

    try {

        $header = array();
        $body = array();
        $footer = array();
        $isauth = $this->sessions->isAuth();
        $header['auth_menu'] = $isauth;

        // INPUTS

        $data['category'] = array($this->format->inputs('uri', 2, array('xss', 'segment'), $this->uri), [3,100]);
        $data['product'] = array($this->format->inputs('uri', 3, array('xss', 'segment'), $this->uri), [3,100]);

        if ($this->uri->getSegment(4)) {
            $data['action'] = array($this->format->inputs('uri', 4, array('xss', 'segment'), $this->uri), [0,4]);
            $data['edit_id'] = array($this->format->inputs('uri', 5, array('xss', 'segment'), $this->uri), [0,6]);
        }

        $assessed = $this->format->assess($data);
        if (!empty($assessed['dirty'])) throw new \Exception($assessed['dirty']);
        if (empty($assessed['clean'])) throw new \Exception('Empty post or no clean key values passed through.');
        $clean = $assessed['clean'];

        // VALID SEGMENT

        $category = array_search($clean['category'], $this->structures->product_category_uris());
        if (!$category) throw new \Exception('Product URI (' . $clean['category'] . '/' . $clean['product'] . ') not found or inactive.');
        $body['category_uri'] = $clean['category'];
        $body['category_name'] = $this->structures->product_category()[$category];

        // PRODUCT

        $product = $this->productsModel->getProduct(array('status' => '31', 'archive' => '30', 'category' => $category, 'uri' => $clean['product']));
        if (empty($product)) throw new \Exception('Product URI (' . $clean['category'] . '/' . $clean['product'] . ') not found or inactive.');
        $body['url'] = '/' . $clean['category'] . '/' . $product['uri'];
        $body['crumbs'] = '<span class="link" data-u="/' . $clean['category'] . '" data-m="1">' . ucfirst($clean['category']) . '</span> / <span class="link" data-u="' . $body['url'] . '" data-m="1">' . $product['name'] . '</span>';
        $body['product'] = $product;

        $versions = $this->productsModel->getVersions(array('product_id' => $product['id'], 'status' => '31', 'archive' => '30'));
        if (!empty($versions)) {
            usort($versions, function($a, $b) {
                return $a['ranking'] <=> $b['ranking'];
            });
        } else {
            $versions = array();
        }

        // IMAGES

        $images = $this->productsModel->getProductImages(array('product_id' => $product['id']));

        if (!empty($images)) {

            usort($images, function($a, $b) {
                return $a['ranking'] <=> $b['ranking'];
            });

            $body['images'] = $images;
            $body['image_config'] = array();
            $count = 0;
            foreach ($images as $image) {
                $count++;
                if ($count === 1) continue;
                $body['image_config'][] = array(

                    'title' => $image['title'],
                    'description' => $image['description'],
                    'file' => $image['file']

            );
            }

        }

        // VERSIONS

        $config = array();

        foreach ($versions as $version_key => $version) {

            // Prices

            $price_result = $this->productsModel->getProductPrices($version['id']);

            $count = 1;

            foreach ($price_result as $price_key => $price) {

                if ($price['start'] > $product['maximum'] || strlen($price['start']) > 3) break;

                if ($price['multiplier'] == '30') {

                    $calculated_price = number_format((($price['base'] / 100) * $price['markup']),2,'.','');

                } else if ($price['multiplier'] == '31') {

                    $calculated_price = number_format($price['base'] + $price['markup'],2,'.','');

                }

                if ($price['end'] > $product['maximum']) $price['end'] = $product['maximum'];

                $prices[$price_key] = array(

                    'start' => $price['start'],
                    'end' => $price['end'],
                    'price' => $this->format->sanitize($calculated_price, 'decimal'),
                    'currency' => $price['currency'],

            );

                if ($count == 1) $original_base_price = number_format($calculated_price * $price['start'],2,'.','');

                $count++;

            }

            // Options

            $options = $this->productsModel->getVersionOptions('products_versions_options', $version['id']);

            if (!empty($options)) {
                usort($options, function($a, $b) {
                    return $a['ranking'] <=> $b['ranking'];
                });
            } else {
                $options = array();
            }

            foreach ($options as $option_key => $option) {

                $conversion = $this->structures->product_markup_multiplier()[$option['multiplier']];
                if (!$conversion) throw new \Exception('Failed to convert posted value (multiplier) in structures.');
                $multiplier = $conversion;

                $options[$option_key] = array(

                    'id' => $option['id'],
                    'title' => $option['title'],
                    'description' => $option['description'],
                    'amount' => $option['base'],
                    'type' => $multiplier

            );

            }

            // Bundles

            $bundles = $this->productsModel->getVersionOptions('products_versions_bundles', $version['id']);

            if (!empty($bundles)) {
                usort($bundles, function($a, $b) {
                    return $a['ranking'] <=> $b['ranking'];
                });
            } else {
                $bundles = array();
            }

            foreach ($bundles as $bundle_key => $bundle) {

                $child_version = $this->productsModel->getVersion(array('id' => $bundle['version_id_child'], 'status' => '31', 'archive' => '30'));
                $child_product = $this->productsModel->getProduct(array('id' => $child_version['product_id'], 'status' => '31', 'archive' => '30'));

                $bundle_prices = $this->productsModel->getProductPrices($bundle['version_id_child']);
                if (empty($bundle_prices)) continue;

                $conversion = $this->structures->product_markup_multiplier()[$bundle['multiplier']];
                if (!$conversion) throw new \Exception('Failed to convert posted value (multiplier) in structures.');
                $multiplier = $conversion;

                $conversion = $this->structures->alignment_rules()[$bundle['alignment_rule']];
                if (!$conversion) throw new \Exception('Failed to convert posted value (alignment rule) in structures.');
                $alignment_rule = $conversion;

                $bundles[$bundle_key] = array(

                    'id' => $bundle['id'],
                    'title' => $bundle['title'],
                    'description' => $bundle['description'],
                    'discount' => $bundle['discount'],
                    'type' => $multiplier,
                    'alignment' => $alignment_rule,
                    'quantifier' => $child_product['quantifier'],
                    'prices' => array()

            );

                foreach ($bundle_prices as $bundle_price) {

                    if ($bundle_price['multiplier'] == '30') {

                        $calculated_bundle_price = number_format((($bundle_price['base'] / 100) * $bundle_price['markup']),2,'.','');

                    } else if ($price['multiplier'] == '31') {

                        $calculated_bundle_price = number_format($bundle_price['base'] + $bundle_price['markup'],2,'.','');

                    }

                    $bundles[$bundle_key]['prices'][] = array(

                        'start' => $bundle_price['start'],
                        'end' => $bundle_price['end'],
                        'price' => $calculated_bundle_price,
                        'currency' => $bundle_price['currency']

                );

                }

            }

            // Data Inputs

            $data_inputs = $this->productsModel->getVersionOptions('products_versions_data', $version['id']);

            if (!empty($data_inputs)) {
                usort($data_inputs, function($a, $b) {
                    return $a['ranking'] <=> $b['ranking'];
                });
            } else {
                $data_inputs = array();
            }

            foreach ($data_inputs as $data_input_key => $data_input) {

                $data_inputs[$data_input_key] = array(

                    'id' => $data_input['id'],
                    'title' => $data_input['title'],
                    'description' => $data_input['description'],
                    'code' => $data_input['code']

            );

            }

            // Compile

            $config[$version['id']] = array(

                'id' => $version['id'],
                'name' => $version['name'],
                'increment' => $product['increment'],
                'minimum' => $product['minimum'],
                'maximum' => $product['maximum'],
                'quantifier' => $this->structures->product_quantifier()[$product['quantifier']],
                'options' => $options,
                'bundles' => $bundles,
                'data_inputs' => $data_inputs,
                'prices' => $prices

        );

        }

        // FEATURES

        $fields = array('features', 'cases', 'inclusions', 'resources', 'requirements', 'specifications', 'integrations', 'faqs_product', 'faqs_purchasing');

        foreach ($fields as $field) {

            $results = $this->productsModel->getProductDetails('products_' . $field, array('product_id' => $product['id']));

            if (!empty($results)) {

                usort($results, function($a, $b) {
                    return $a['ranking'] <=> $b['ranking'];
                });

                foreach ($results as $result_key => $result) {

                    $version_list = array();

                    foreach ($versions as $version) {

                        $matches = $this->productsModel->getVersionAssociations(array('version_id' => $version['id'], 'type' => $field, 'target_id' => $result['id']));
                        if (!empty($matches)) {
                            foreach ($matches as $match) {
                                $match['name'] = $version['name'];
                                $version_list[] = $match;
                            }
                        }

                    }

                    if (empty($version_list)) $version_list = array();

                    $results[$result_key]['associations'] = $version_list;

                }

                $body[$field] = $results;

            }

        }

        // CONFIG

        $header['assets'] = $this->utilities->assets();
        $footer['cache'] = csrf_hash(); // Post CSRF Token
        $body['cache_config'] = array();
        $body['product_types'] = $this->structures->product_type();
        $body['product_statuses'] = $this->structures->status();
        $body['product_quantifiers'] = $this->structures->product_quantifier();
        $body['cache_config'] = $config;
        $body['edit_id'] = '';
        $body['initial'] = array_values($config)[0];

        // EDIT CART ITEM

        if ($clean['action']
            && $clean['action'] === 'edit'
            && $clean['edit_id']
            && is_numeric($clean['edit_id'])
            && strlen($clean['edit_id']) === 6
            && $_SESSION['invoice'][$clean['edit_id']]) {

            $edit_item = $_SESSION['invoice'][$clean['edit_id']];
            $new_initial = $config[$_SESSION['invoice'][$clean['edit_id']]['version_id']];
            if (!empty($new_initial)) {

                if ($edit_item['quantity'] >= $new_initial['minimum'] && $edit_item['quantity'] <= $new_initial['maximum']) {

                    foreach ($new_initial['prices'] as $price) {

                        if ($edit_item['quantity'] >= $price['start'] && $edit_item['quantity'] <= $price['end']) {

                            $body['initial'] = $new_initial;
                            $body['edit_id'] = $clean['edit_id'];
                            $body['edit_quantity'] = $edit_item['quantity'];
                            $body['edit_options'] = (!empty($edit_item['options'])) ? $edit_item['options'] : array();
                            $body['edit_bundles'] = (!empty($edit_item['bundles'])) ? $edit_item['bundles'] : array();
                            $body['edit_data_inputs'] = (!empty($edit_item['data_inputs'])) ? $edit_item['data_inputs'] : array();
                            break;

                        }

                    }

                }

            }

            if (empty($body['edit_id'])) {

                $this->utilities->htmlResponse(302, base_url() . $clean['category'] . '/' . $clean['product'], false);
                return;

            }

        }

        // BANNER

        $header['banner'] = 'product';
        $header['banner_data'] = array('name' => $product['name']);

        // RENDER

        echo view('structure/header', $header);
        echo view('pages/product', $body);
        echo view('structure/footer', $footer);

    } catch (\Exception $error) {

        $log = 'Controller failure (' . $error . ')';
        $this->events->log('system', null, 'Warning', NULL, $log, true, false);
        if ($this->utilities->ajaxHtml() === 'ajax') $this->utilities->ajaxResponse(200, 403, '', false);
        else $this->utilities->htmlResponse(302, null, false);

    }

}