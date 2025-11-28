<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Service\Admin;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class CartViewerService
{
    private $connection;

    public function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getCartItemsByCustomerId($customerId)
    {
        $sql = "SELECT sess_data FROM eccube_session WHERE sess_data LIKE :search";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('search', '%"customer_id";i:' . (int)$customerId . ';%', \PDO::PARAM_STR);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch()) {
            $sessionData = $row['sess_data'];
            $data = unserialize($sessionData);
            if (isset($data['_sf2_attributes']['eccube.cart'])) {
                $cart = $data['_sf2_attributes']['eccube.cart'];
                $items = $cart->getCartItems();
                foreach ($items as $item) {
                    $results[] = [
                        'product_name' => $item->getProductClass()->getProduct()->getName(),
                        'quantity' => $item->getQuantity(),
                        'price' => $item->getPrice(),
                    ];
                }
            }
        }

        return $results;
    }
}