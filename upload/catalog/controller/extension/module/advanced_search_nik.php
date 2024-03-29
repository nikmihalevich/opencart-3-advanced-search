<?php
class ControllerExtensionModuleAdvancedSearchNik extends Controller {
	public function index() {
		$this->load->language('extension/module/advanced_search_nik');

        $this->load->model('setting/setting');
        $this->load->model('extension/module/advanced_search_nik');

        $data = $this->model_setting_setting->getSetting('module_advanced_search_nik');

        $data['categories'] = $this->model_extension_module_advanced_search_nik->getCategories();

		return $this->load->view('extension/module/advanced_search_nik', $data);
	}

    public function autocomplete() {
        $json = array();

        if (isset($this->request->get['filter_name'])) {
            $this->load->model('catalog/product');
            $this->load->model('extension/module/advanced_search_nik');
            $this->load->model('setting/setting');
            $this->load->model('tool/image');

            $this->load->language('extension/module/advanced_search_nik');

            $data = $this->model_setting_setting->getSetting('module_advanced_search_nik');

            if(!isset($data['module_advanced_search_nik_count_items_for_display'])) {
                $data['module_advanced_search_nik_count_items_for_display'] = 10;
            }

            if(!isset($data['module_advanced_search_nik_count_brands_for_display'])) {
                $data['module_advanced_search_nik_count_brands_for_display'] = 10;
            }

            if(!isset($data['module_advanced_search_nik_count_category_for_display'])) {
                $data['module_advanced_search_nik_count_category_for_display'] = 10;
            }

            if (isset($this->request->get['filter_name'])) {
                $filter_name = $this->request->get['filter_name'];
            } else {
                $filter_name = '';
            }

            if (isset($this->request->get['filter_category'])) {
                $filter_category = $this->request->get['filter_category'];
            } else {
                $filter_category = '';
            }

            if ($data['module_advanced_search_nik_count_items_for_display']) {
                $limit = (int)$data['module_advanced_search_nik_count_items_for_display'];
            } else {
                $limit = 10;
            }

            $filter_data = array(
                'filter_name'        => $filter_name,
                'filter_category_id' => $filter_category,
                'start'              => 0,
                'limit'              => $limit,
                'favorite_products'  => isset($data['module_advanced_search_nik_favorite_products']) ? $data['module_advanced_search_nik_favorite_products'] : array()
            );

            $favorites_products = $this->model_extension_module_advanced_search_nik->getProducts($filter_data);
            unset($filter_data['favorite_products']);

            $ordered = $this->model_extension_module_advanced_search_nik->getOrderedProducts();
            $filter_data['ordered_products'] = $ordered;

            $ordered_products = $this->model_extension_module_advanced_search_nik->getProducts($filter_data);
            unset($filter_data['ordered_products']);

            $all_products = $this->model_extension_module_advanced_search_nik->getProducts($filter_data);

            $results = array_merge($favorites_products, $ordered_products, $all_products);

            $results = array_unique($results, SORT_REGULAR);

            $results = array_slice($results, 0, $limit);

            if ($data['module_advanced_search_nik_display_category']) {
                $products_categories = array();
                foreach ($results as $result) {
                    $product_categories = $this->model_extension_module_advanced_search_nik->getCategoriesByProduct($result['product_id']);

                    foreach ($product_categories as $product_category) {
                        if (!in_array($product_category, $products_categories)) {
                            $products_categories[] = $product_category;
                        }
                    }
                }

                $categories_info = array();

                foreach ($products_categories as $product_category) {
                    $categories_info[] = $this->model_extension_module_advanced_search_nik->getCategory($product_category['category_id']);
                }

                $categories_info = array_slice($categories_info, 0, $data['module_advanced_search_nik_count_category_for_display']);

                foreach ($categories_info as $category_info) {
                    $category_parents = $this->model_extension_module_advanced_search_nik->getCategoryParents($category_info['parent_id']);

                    if (isset($category_info['parent_id']) && $category_info['parent_id'] != "0") {
                        $link = $this->url->link('product/category', 'path=' . $category_info['parent_id'] . '_' . $category_info['category_id']);
                    } else {
                        $link = $this->url->link('product/category', 'path=' . $category_info['category_id']);
                    }

                    $json[] = array(
//                    'product_id' => $category_info['category_id'],
                        'name' => $category_info['name'],
                        'type' => 'category',
                        'description' => $category_parents ? $category_parents['name'] : '',
                        'link' => $link
                    );
                }
            }


            if ($data['module_advanced_search_nik_display_brands']) {
                $products_manufacturers = array();

                foreach ($results as $result) {
                    $product_manufacturer = $this->model_extension_module_advanced_search_nik->getManufacturer($result['manufacturer_id']);
                    if (!in_array($product_manufacturer, $products_manufacturers)) {
                        $products_manufacturers[] = $product_manufacturer;
                    }
                }

                $products_manufacturers = array_slice($products_manufacturers, 0, $data['module_advanced_search_nik_count_brands_for_display']);

                foreach ($products_manufacturers as $product_manufacturer) {
                    $json[] = array(
//                    'product_id' => $product_manufacturer['manufacturer_id'],
                        'name' => $product_manufacturer['name'],
                        'image' => $this->model_tool_image->resize($product_manufacturer['image'], 20, 20),
                        'type' => 'manufacturer',
                        'description' => $this->language->get('text_brand'),
                        'link' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $product_manufacturer['manufacturer_id'])
                    );
                }
            }

            foreach ($results as $result) {
                $json[] = array(
//                    'product_id'  => $result['product_id'],
                    'search_name' => strip_tags(html_entity_decode($result['name'] . ' '. $result['manufacturer'], ENT_QUOTES, 'UTF-8')),
                    'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                    'model'       => $result['model'],
                    'manufacturer'=> $result['manufacturer'],
                    'price'       => $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                    'image'       => $data['module_advanced_search_nik_display_product_image'] ? $this->model_tool_image->resize($result['image'], 60, 60) : false,
                );
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
