<?php
/**
 *
 *
 * @author        肖武 <five@v5ip.com>
 * @datetime      2018/4/19 下午9:20
 */


include './captcha.php';

$arr_option = [];

$obj = new \Five\Captcha($arr_option);

$code = $obj->creat();

