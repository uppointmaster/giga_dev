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

namespace Customize\Service\Admin;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class CartViewerService
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * カート内容を取得
     *
     * @param $CustomerId int 会員ID
     *
     * @return array
     */
    public function getCartItemsByCustomerId($CustomerId)
    {
        $sql = "SELECT 
                    p.name as product_name,
                    pc.id as product_class_id,
                    ci.quantity,
                    ci.price
                FROM
                    dtb_product p
                JOIN
                    dtb_product_class pc ON pc.product_id = p.id
                JOIN
                    dtb_cart_item ci ON ci.product_class_id = pc.id
                JOIN
                    dtb_cart c ON c.id = ci.cart_id
                WHERE
                    c.customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        while ($row = $result->fetchAssociative()) {
            $results[] = [
                'product_name' => $row['product_name'], //$item->getProductClass()->getProduct()->getName(),
                'product_class_id' => $row['product_class_id'],
                'quantity' => $row['quantity'], //$item->getQuantity(),
                'price' => $row['price'], //$item->getPrice(),
                'total_price' => $row['price'] * $row['quantity'],
            ];
        }

        return $results;
    }
    
    /**
     * カートに追加できる商品を取得
     *
     * @param $CustomerId int 会員ID
     *
     * @return array
     */
    public function getProductsCanAdd($CustomerId)
    {
        $sql = "SELECT 
                    p.name as product_name,
                    pc.id as product_class_id,
                    pc.price02 as price
                FROM
                    dtb_product p
                JOIN
                    dtb_product_class pc ON pc.product_id = p.id
                WHERE
                    p.product_status_id =  1
                AND
                    ( pc.stock > 0 OR pc.stock_unlimited = 1)
                    ";
        
        $product_ids_cart = $this->getProductIdsAdded($CustomerId);
        if(!empty($product_ids_cart)){
            $sql.= "
                AND
                    p.id NOT IN (" . implode(",", $product_ids_cart) . ")
                    ";
        }
        $result = $this->connection->executeQuery($sql, []);
        $results = [];
        while ($row = $result->fetchAssociative()) {
            $results[] = [
                'product_name' => $row['product_name'], //$item->getProductClass()->getProduct()->getName(),
                'product_class_id' => $row['product_class_id'],
                'price' => $row['price'],
            ];
        }

        return $results;
    }
    
    /**
     * カートに追加されている商品を取得
     *
     * @param $CustomerId int 会員ID
     *
     * @return array
     */
    public function getProductIdsAdded($CustomerId)
    {  
        $sql = "SELECT 
                    p.id as product_id
                FROM
                    dtb_product p
                JOIN
                    dtb_product_class pc ON pc.product_id = p.id
                JOIN
                    dtb_cart_item ci ON ci.product_class_id = pc.id
                JOIN
                    dtb_cart c ON c.id = ci.cart_id
                WHERE
                    c.customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        while ($row = $result->fetchAssociative()) {
            $results[] = $row['product_id'];
        }

        return $results;
    }
    
    /**
     * カートに商品を追加
     *
     * @param $CustomerId int 会員ID
     * @param $ProductIds array 商品ID
     * @param $ProductPrices array 価格
     *
     * @return array
     */
    public function addProductInCart($CustomerId, $ProductIds, $ProductPrices)
    {  
        $sql = "SELECT 
                    id as cart_id
                FROM
                    dtb_cart
                WHERE
                    customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        $row = $result->fetchAssociative();
        if(empty($row['cart_id'])){
            $sql = "INSERT INTO
                        dtb_cart (
                            customer_id,
                            cart_key,
                            pre_order_id,
                            total_price,
                            delivery_fee_total,
                            sort_no,
                            create_date,
                            update_date,
                            add_point,
                            use_point,
                            discriminator_type
                            )
                    VALUES
                        (
                            '".$CustomerId."',
                            '".$CustomerId."_1',
                            '',
                            0,
                            0,
                            0,
                            now(),
                            now(),
                            0,
                            0,
                            'cart'
                            )";
            $this->connection->executeUpdate($sql);
            $row['cart_id'] = $this->connection->lastInsertId('id');
        }
        $results[] = $row['cart_id'];
        if(is_array($ProductIds)){
            foreach($ProductIds as $pcid => $qty){
                if(!$qty) continue;
                $price = $ProductPrices[$pcid] * 1.1;
                $sql = "INSERT INTO
                            dtb_cart_item
                            (product_class_id, cart_id, price, quantity, point_rate, discriminator_type)
                        VALUES
                            ('".$pcid."', '".$row['cart_id']."', '".$price."','".$qty."', '0', 'cartitem')";
                $this->connection->executeUpdate($sql);
            }
        }

        return $results;
    }

    /**
     * カートから商品を削除
     *
     * @param $CustomerId int 会員ID
     * @param $ProductIds array 商品ID
     *
     * @return array
     */
    public function deleteProductInCart($CustomerId, $ProductIds)
    {  
        $sql = "SELECT 
                    id as cart_id
                FROM
                    dtb_cart
                WHERE
                    customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        $row = $result->fetchAssociative();
        $results[] = $row['cart_id'];
        if(is_array($ProductIds)){
            foreach($ProductIds as $pcid => $qty){
                if(!$qty) continue;
                $sql = "DELETE FROM
                            dtb_cart_item
                        WHERE
                            product_class_id = '".$pcid."' AND cart_id = '".$row['cart_id']."'";
                $this->connection->executeUpdate($sql);
            }
        }

        return $results;
    }

    /**
     * カートから商品数量を変更
     *
     * @param $CustomerId int 会員ID
     * @param $ProductIds array 商品ID
     *
     * @return array
     */
    public function updateProductInCart($CustomerId, $ProductIds)
    {  
        $sql = "SELECT 
                    id as cart_id
                FROM
                    dtb_cart
                WHERE
                    customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        $row = $result->fetchAssociative();
        $results[] = $row['cart_id'];
        if(is_array($ProductIds)){
            foreach($ProductIds as $pcid => $qty){
                if(!$qty) continue;
                $sql = "UPDATE
                            dtb_cart_item
                        SET
                            quantity = '".$qty."'
                        WHERE
                            product_class_id = '".$pcid."' AND cart_id = '".$row['cart_id']."'";
                $this->connection->executeUpdate($sql);
            }
        }

        return $results;
    }

    /**
     * カート内容(商品数)を取得
     *
     * @param $CustomerId int 会員ID
     *
     * @return array
     */
    public function getCartItemsCountByCustomerId($CustomerId)
    {
        $sql = "SELECT 
                    p.name as product_name,
                    pc.id as product_class_id,
                    ci.quantity,
                    ci.price
                FROM
                    dtb_product p
                JOIN
                    dtb_product_class pc ON pc.product_id = p.id
                JOIN
                    dtb_cart_item ci ON ci.product_class_id = pc.id
                JOIN
                    dtb_cart c ON c.id = ci.cart_id
                WHERE
                    c.customer_id =  :customer_id
                    ";
        $result = $this->connection->executeQuery($sql, ['customer_id' => $CustomerId, \PDO::PARAM_STR]);
        $results = [];
        $quantity = 0;
        while ($row = $result->fetchAssociative()) {
            $results[] = [
                'product_name' => $row['product_name'], //$item->getProductClass()->getProduct()->getName(),
                'product_class_id' => $row['product_class_id'],
                'quantity' => $row['quantity'], //$item->getQuantity(),
                'price' => $row['price'], //$item->getPrice(),
                'total_price' => $row['price'] * $row['quantity'],
            ];
            $quantity += $row['quantity'];
        }

        return $quantity;
    }
}