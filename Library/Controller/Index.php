<?php
/**
 * shadowsocks-panel
 * A simple Shadowsocks management system
 * Author: Sendya <18x@loacg.com>
 */
namespace Controller;

use Core\Template;

class Index
{

    /**
     * @Home
     * @Route /Index
     */
    public function index()
    {
        //Template::setView('Home/index');
    }
}
