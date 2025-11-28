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

namespace Customize\Service\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Customize\Service\Admin\CartViewerService;

class CartViewerTwigExtension extends AbstractExtension
{
    private $cartViewerService;

    public function __construct(CartViewerService $cartViewerService)
    {
        $this->cartViewerService = $cartViewerService;
    }

    public function getGlobals(): array
    {
        return [
            'cart_viewer' => $this->cartViewerService,
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('cart_viewer_count', [$this->cartViewerService, 'getCartItemsCountByCustomerId']),
        ];
    }
}