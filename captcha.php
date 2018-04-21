<?php
/**
 * 验证码类
 *
 * @author        肖武 <five@v5ip.com>
 * @datetime      2018/4/19 下午9:17
 */

namespace Five;

class Captcha {

    //设置选项
    protected $arr_option = [
        'min_len'     => 4, //生成验证码最小字符数
        'max_len'     => 6, //生成验证码最大字符数
        'chars'       => 'abcdefghjkmnpqrstuvwxy3456789',  //用于生成验证码的字符
        'scale'       => 2, //图片质量, 数值越大越清晰, 处理速度越慢. 1: low, 2: medium, 3: high
        'width'       => 100,
        'height'      => 40,
        'bg_color'    => [255, 255, 255], //背景色
        'fg_color'    => [27, 78, 181], //前景色(文字颜色)
        'max_degree'  => 8, //字符左右倾斜最大角度
    ];

    /**
     * 字体信息
     * @var array
     * spacing : 字符间距, 负数为粘连
     * minSize : 字符最小尺寸
     * maxSize : 字符最大尺寸
     * font    : 字体文件
     */
    protected $fonts = array(
        'Antykwa'  => array(35, 10, 'spacing' => -3, 'minSize' => 27, 'maxSize' => 30, 'font' => 'AntykwaBold.ttf'),
        'Candice'  => array(32, 9, 'spacing' => -2, 'minSize' => 28, 'maxSize' => 31, 'font' => 'Candice.ttf'),
        'DingDong' => array(34, 9, 'spacing' => -2, 'minSize' => 24, 'maxSize' => 30, 'font' => 'Ding-DongDaddyO.ttf'),
        'Duality'  => array(35, 10, 'spacing' => -2, 'minSize' => 30, 'maxSize' => 38, 'font' => 'Duality.ttf'),
        //'Heineken' => array(34, 9, 'spacing' => -2, 'minSize' => 24, 'maxSize' => 30, 'font' => 'Heineken.ttf'), //没有数字
        'Jura'     => array(33, 10, 'spacing' => -2, 'minSize' => 28, 'maxSize' => 32, 'font' => 'Jura.ttf'),
        'StayPuft' => array(30, 10, 'spacing' => -1, 'minSize' => 28, 'maxSize' => 32, 'font' => 'StayPuft.ttf'),
        'Times'    => array(32, 8, 'spacing' => -2.5, 'minSize' => 28, 'maxSize' => 34, 'font' => 'TimesNewRomanBold.ttf'),
        'VeraSans' => array(32, 8, 'spacing' => -1, 'minSize' => 20, 'maxSize' => 28, 'font' => 'VeraSansBold.ttf'),
    );

    protected $code     = ''; //验证码
    protected $code_len = 0; //验证码字符数

    /**
     * Captcha constructor.
     *
     * @param array $option 覆盖有效key, 无效key忽略
     */
    function __construct($option = []) {
        foreach ($option as $k => $v) {
            if (isset($this->arr_option[$k])) {
                $this->arr_option[$k] = $v;
            }
        }
    }

    /**
     * 
     * @return string
     */
    public function creat() {
        //生成验证码
        $this->makeCode();

        //初始化图片
        list($im, $arr_xy) = $this->initImage();

        //扭曲图片
        list($im, $w, $h) = $this->waveImage($im, $arr_xy);

        //缩放
        $im = $this->reSize($im, $w, $h, $this->arr_option['width'], $this->arr_option['height']);

        //输出图片
        $this->outputImage($im);
        
        return $this->code;
    }

    /**
     * 扭曲图像
     * @param $src_im
     * @param $arr_xy
     *
     * @return array
     */
    protected function waveImage($src_im, $arr_xy) {
        $amplitude_x = 5; //x方向横波振幅
        $amplitude_y = 10; //y方向横波振幅

        $src_width  = $arr_xy[2] - $arr_xy[0];
        $src_height = $arr_xy[3] - $arr_xy[1];

        $width  = $src_width + $amplitude_y * 2;
        $height = $src_height + $amplitude_x * 2;

        //画布
        $dst_im = imagecreatetruecolor($width, $height);

        //背景颜色
        $color = $this->arr_option['bg_color'];
        //$color[0] += 50;
        $bg = imagecolorallocate($dst_im, $color[0], $color[1], $color[2]);
        imagefilledrectangle($dst_im, 0, 0, $width, $height, $bg);

        //竖线错位 src_im --> dst_im
        $w_degree = mt_rand(90, 200) * $this->code_len; //宽对应的角度值
        $this->testLog($w_degree / $this->code_len);
        for ($i = 1; $i <= $src_width; $i++) {
            imagecopy(
                $dst_im
                , $src_im
                , $amplitude_y + $i, $amplitude_x + sin($i / $src_width * $w_degree * M_PI / 180) * $amplitude_x
                , $arr_xy[0] + $i, $arr_xy[1]
                , 1, $src_height
            );
        }
        imagedestroy($src_im);

        //横线错位
        $y_degree = mt_rand(180, 260);
        for ($i = 1; $i < $height; $i++) {
            imagecopy(
                $dst_im
                , $dst_im
                , sin($i / $height * $y_degree * M_PI / 180) * $amplitude_y, $i - 1
                , 0, $i
                , $width, 1
            );
        }

        return [$dst_im, $width, $height];
    }

    /**
     * 初始化验证码图片
     *
     * @return array
     */
    protected function initImage() {
        $font_name = array_rand($this->fonts); //* Antykwa, Candice, DingDong, * Duality, Heineken, *Jura, StayPuft, Times, VeraSans
        $font      = $this->fonts[$font_name];

        $scale   = $this->arr_option['scale'];
        $space   = 10 * $scale;  //空白(内边距)
        $char_w  = 40 * $scale; //字符宽度
        $width   = $char_w * $this->code_len + $space * 2; //图像宽度
        $height  = $char_w + 15 * $scale; //图像高度
        $write_x = $space; //写文字x坐标
        $write_y = $char_w; //写文字y坐标

        $this->testLog($this->code, $width, $height, $font_name);

        //画布
        $im = imagecreatetruecolor($width, $height);

        //背景颜色
        $color = $this->arr_option['bg_color'];
        $bg    = imagecolorallocate($im, $color[0], $color[1], $color[2]);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        //var_dump($im, $width);
        //$this->outputImage($im);exit;

        //前景色
        $color = $this->arr_option['fg_color'];
        $fg    = imagecolorallocate($im, $color[0], $color[1], $color[2]);

        //循环写字符串
        $str    = $this->code; //$this->arr_option['chars'];
        $len    = $this->code_len;
        $str_xy = [0, $height, 0, 0]; //字符串的左上角和右下角坐标

        for ($i = 0; $i < $len; $i++) {
            $c      = $str{$i}; //字符
            $degree = mt_rand(-$this->arr_option['max_degree'], $this->arr_option['max_degree']); //字符倾斜角度值

            $coords = imagettftext(
                $im  //图像资源
                , $font['maxSize'] * $scale  //字体大小,像素值
                , $degree   //倾斜角度
                , $write_x, $write_y   //坐标
                , $fg //文字颜色
                , 'fonts/' . $font['font']  //字体文件
                , $c   //字符
            );

            $write_x = min($coords[2], $coords[4]) + $font['spacing'] * $scale; //下一个字符的写入位置

            //字符串范围
            if ($i == 0) {
                $str_xy[0] = min($coords[0], $coords[6]); //字符串最小x坐标
            }
            if ($i + 1 == $len) {
                $str_xy[2] = max($coords[2], $coords[4]); //字符串最大x坐标
            }
            $str_xy[1] = min($str_xy[1], $coords[5], $coords[7]); //字符串最小y坐标
            $str_xy[3] = max($str_xy[3], $coords[1], $coords[3]); //字符串最大y坐标
        }

        //$this->testRectangle($im, $str_xy); //标记文字区域矩形

        return [$im, $str_xy];
    }

    /**
     * 缩放
     *
     * @param $src_im
     * @param $src_w
     * @param $src_h
     * @param $dst_w
     * @param $dst_h
     *
     * @return resource
     */
    protected function reSize($src_im, $src_w, $src_h, $dst_w, $dst_h) {
        $dst_im = imagecreatetruecolor($dst_w, $dst_h);
        imagecopyresampled($dst_im, $src_im,
            0, 0, 0, 0,
            $dst_w, $dst_h,
            $src_w, $src_h
        );
        imagedestroy($src_im);

        return $dst_im;
    }

    /**
     * 检查验证码是否正确
     *
     * @param $code
     */
    public function check($code) {

    }

    /**
     * 输出图片
     */
    protected function outputImage($im) {
        header("Content-type: image/png");
        imagepng($im);
    }

    /**
     * 生成验证码字符串
     *
     * @return string
     */
    protected function makeCode() {
        $max_idx        = strlen($this->arr_option['chars']) - 1;
        $this->code_len = mt_rand($this->arr_option['min_len'], $this->arr_option['max_len']);

        $code = '';
        for ($i = 0; $i < $this->code_len; $i++) {
            $idx  = mt_rand(0, $max_idx);
            $code .= $this->arr_option['chars']{$idx};
        }

        $this->code = $code;

        //$this->code_len = 4;
        //$this->code = 't9rf';
    }

    //画一个矩形 (左上角, 右下角)
    protected function testRectangle($img, $arr_xy) {
        $fg = imagecolorallocate($img, 0, 0, 0);
        imageline($img, $arr_xy[0], $arr_xy[1], $arr_xy[2], $arr_xy[1], $fg);
        imageline($img, $arr_xy[2], $arr_xy[1], $arr_xy[2], $arr_xy[3], $fg);
        imageline($img, $arr_xy[2], $arr_xy[3], $arr_xy[0], $arr_xy[3], $fg);
        imageline($img, $arr_xy[0], $arr_xy[3], $arr_xy[0], $arr_xy[1], $fg);
    }

    protected function test2Line($img, $x, $y) {
        $fg = imagecolorallocate($img, 250, 0, 0);
        imageline($img, 0, $y, $x, $y, $fg);
        imageline($img, $x, 0, $x, $y, $fg);
    }

    protected function testLog() {
        $arr = func_get_args();
        $str = date('[Y-m-d H:i:s]') . json_encode($arr, JSON_UNESCAPED_UNICODE) . "\n";

        return file_put_contents('/tmp/captcha.log', $str, FILE_APPEND);
    }
}