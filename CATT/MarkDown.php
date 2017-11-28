<?php
/**
 * Markdown 解释器
 *
 * @author    CATT-L <catt-l.com>
 * 
 */

// 原本以为改换行一下子就能改好
// 结果发现好多BUG啊！
// 改了一天总算改好了
// 应该没什么大问题
// 2017-11-28 15:49:00
/**
 * @version   1.1
 * 结束时间 2017-11-28 15:49:10
 * 开始时间 2017-11-28 08:36:19
 */

// 嗨呀 搞错了一个规则！
// Markdown的回车换行是不渲染的 QAQ
// [空格][空格][回车]才是换行
// 双换行结束匹配
// 下午再改 吃饭吃饭
// 2017-11-24 14:09:47

// 终于搞定了！
// 2017年11月24日14:09:15
/**
 * @version   1.0
 * 结束时间 2017-11-24 14:08:06
 * 开始时间 2017-11-23 09:27:03
 */



namespace CATT;

class MarkDown{

	private $version	= "1.0";
	private $markStr 	= "";
	private $idList	 	= array();
	private $markTree	= array();
	private $markHtml 	= "";

	private $tabWidth 	= 4;
	private $brBreak 	= 1;

	public function __construct(){
		$this->init();
	}

	// 初始化
	private function init(){
	}

	// 这是Markdown转换Html的魔法接口
	public function parse($str){

		// 排除 UTF-8 BOM 异教徒
		$str = preg_replace('/^\xEF\xBB\xBF|\x1A/', '', $str);

		// 统一换行标准
		$str = preg_replace('/\r\n?/', "\n", $str);

		// Tab缩进转换成空格
		$str = preg_replace_callback('/^.*\t.*$/m', array($this, 'cbDetab'), $str);

		// 消除空白
		$str = preg_replace('/^[ ]+$/m', '', $str);

		// 保证双换行结束文本
		$str .= "\n\n";

		// 初始化全局变量
		$this->markStr = $str;
		
		// 结构成数组搭建框架 准备构造魔法回路
		$markArr = $this->parseArr($str);

		// 获取ID
		$this->idList = $this->searchID($markArr);

		// 构建块级魔法回路
		$markArr = $this->blockTransform($markArr);

		// 构建内联魔法回路
		$markArr = $this->inlineTransform($markArr);

		// 魔法回路构建完成 全局同步 准备充能
		$this->markTree = $markArr;

		// 魔力充能
		$html = $this->parseHTML($markArr);

		// 充能完成 全局同步
		$this->markHtml = $html;

		// 构造术式 准备释放
		$enchant = array();

		$enchant['raw'] = $this->markStr;
		$enchant['tree'] = $this->markTree;
		$enchant['html'] = $this->markHtml;
		$enchant['id'] = $this->idList;

		// 释放魔法
		return $enchant;
	}

	// 搜索ID
	private function searchID($root){

		$idList = array();

		$child = $root['child'];
		foreach ($child as $v) {
			if($re = $this->parseID($v)){
				$id = array();
				$id['url'] = $re['url'];
				$id['title'] = $re['title'];

				$idList[$re['id']] = $id;
			}
		}

		return $idList;
	}

	// 递归遍历叶子节点 构造HTML字符串
	private function parseHTML($tree){

		$html = "";

		$type = $tree['type'];
		$display = $tree['display'];
		$child = $tree['child'];

		foreach ($child as $v) {
			if($v['child']) {
				$tag = $v['type'];

				$inner = $this->parseHTML($v);

				switch ($tag) {
					case 'a':
						$html .= "<a href=\"".$v['url']."\">".$inner."</a>";
						break;
					
					case 'preMark':
						$html .= "<pre class=\"pre\">\n".$inner."</pre>\n";
						break;
					case 'blockquote':
					case 'ul':
					case 'ol':
						if($v['display'] == "block")
							$html .= "<".$tag.">\n".$inner."</".$tag.">\n";
						else
							$html .= $inner;
						break;
					case '':
							$html .= $inner;
						break;
					default:
						$html .= "<".$tag.">".$inner."</".$tag.">\n";
						break;
				}

			}

			// 遍历叶子 转换拼接
			else {
				$tag = $v['type'];

				// 代码块
				if($type == "preMark" || $type == "pre"){
					$html .= $v['text']."<br>";

				} else switch ($tag) {
					case 'br':
						if($v['display'] == "block") $html .= "<br>";
						break;
					case 'pMark':
						$html .= "<br>";
						break;
					case 'hr':
						$html .= "<".$tag.">";
						break;

					case 'img':
						$html .= "<img src=\"".$v['url']."\" alt=\"".$v['alt']."\" title=\"".$v['title']."\">\n";
						break;

					case 'li':
						$html .= "<".$tag.">".$v['text']."</".$tag.">\n";
						break;
					
					default:
						$html .= $v['text'];
						break;
				}
			}
		}

		return $html;
	}

	// 递归遍历叶子节点
	// 顺序调用魔法处理内联
	private function inlineTransform($tree){
		$type = $tree['type'];
		$display = $tree['display'];
		$child = $tree['child'];

		// 代码块不处理
		if($type != "pre" && $type != "preMark")
			// 遍历幼儿节点 处理内联元素
			foreach ($child as $k => $v) {

				// 忽略的节点
				if($v['type'] == "hr" || $v['type'] == "br" || $v['type'] == "img" || $v['type'] == "kbd" || $v['type'] == "code" || $v['type'] == "fin") continue;

				// 幼儿节点
				if(!$v['child']){

					// 执行转义魔法
					$v = $this->inlineEscape($v);
					$child[$k] = $v;


					// 执行链接识别魔法
					// 像这种操作一定会搞出很多孩子
					// 所以要递归逐个检查孩子需要怎么处理
					if($re = $this->inlineLink($v)){
						$child[$k] = $this->inlineTransform($re);
						continue;
					}

					// 执行内联代码匹配
					if($re = $this->inlineCode($v)){
						$child[$k] = $this->inlineTransform($re);
						continue;
					}

					// 执行自动链接识别的魔法 包括邮箱和网址
					if($re = $this->autoLink($v)){
						$child[$k] = $this->inlineTransform($re);
						continue;
					}

					// **变粗魔法** *变弯魔法* -下划魔法- --腰斩魔法-- 
					// __变粗魔法__ _变弯魔法_ ~下划魔法~ ~~腰斩魔法~~ 
					if($re = $this->inlineExtra($v)){
						$child[$k] = $this->inlineTransform($re);
						continue;
					}
				}

				// 递归孩子
				else {

					$child[$k] = $this->inlineTransform($v);
				}
			}

		$tree['child'] = $child;
		return $tree;
	}

	// 强调效果
	private function inlineExtra($node){
		// **变粗魔法** *变弯魔法* -下划魔法- --腰斩魔法-- 
		// __变粗魔法__ _变弯魔法_ ~下划魔法~ ~~腰斩魔法~~ 

		$str = $node['text'];

		// 又弯又粗 或者 下滑腰斩
		$magic = '/([*]*)[*]{3}(.*?)[*]{3}([*]*)|([_]*)[_]{3}(.*?)[_]{3}([_]*)|([~]*)[~]{3}(.*?)[~]{3}([~]*)|([-]*)[-]{3}(.*?)[-]{3}([-]*)/';

		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				array_push($kids, $this->createChild(array('text'=>$v)));

				// 内联数组
				if($re[0]){

					$match = array_shift($re[0]);

					$sign = substr($match, 0, 1);

					// 斜体加粗 下划贯穿
					switch ($sign) {
						case '*':
							$text = array_shift($re[1]).array_shift($re[2]).array_shift($re[3]);
							$cType = "i"; $pType = "b";
							break;
						case '_':
							$text = array_shift($re[4]).array_shift($re[5]).array_shift($re[6]);
							$cType = "i"; $pType = "b";
							break;
						case '~':
							$text = array_shift($re[7]).array_shift($re[8]).array_shift($re[9]);
							$cType = "u"; $pType = "s";
							break;
						case '-':
							$text = array_shift($re[10]).array_shift($re[11]).array_shift($re[12]);
							$cType = "u"; $pType = "s";
							break;

					}

					$attr = array();
					$attr['type'] = "fin";
					$attr['text'] = $text;
					$leaf = $this->createChild($attr);

					$attr['text'] = $sign.$text.$sign;
					$attr['type'] = $cType;

					$child = $this->createChild($attr);

					array_push($child['child'], $leaf);

					$attr['text'] = $match;
					$attr['type'] = $pType;
					$parent = $this->createChild($attr);

					array_push($parent['child'], $child);

					array_push($kids, $parent);
				}
			}

			$node['child'] = $kids;
			return $node;
		}

		// 变粗 腰斩
		$magic = '/([*]*)[*]{2}(.*?)[*]{2}([*]*)|([_]*)[_]{2}(.*?)[_]{2}([_]*)|([~]*)[~]{2}(.*?)[~]{2}([~]*)|([-]*)[-]{2}(.*?)[-]{2}([-]*)/';

		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				array_push($kids, $this->createChild(array('text'=>$v)));

				// 内联数组
				if($re[0]){

					$match = array_shift($re[0]);

					$sign = substr($match, 0, 1);

					// 加粗 贯穿
					switch ($sign) {
						case '*':
							$text = array_shift($re[1]).array_shift($re[2]).array_shift($re[3]);
							$type = "b";
							break;
						case '_':
							$text = array_shift($re[4]).array_shift($re[5]).array_shift($re[6]);
							$type = "b";
							break;
						case '~':
							$text = array_shift($re[7]).array_shift($re[8]).array_shift($re[9]);
							$type = "s";
							break;
						case '-':
							$text = array_shift($re[10]).array_shift($re[11]).array_shift($re[12]);
							$type = "s";
							break;

					}

					$attr = array();
					$attr['type'] = "fin";
					$attr['text'] = $text;
					$leaf = $this->createChild($attr);

					$attr['text'] = $match;
					$attr['type'] = $type;

					$child = $this->createChild($attr);

					array_push($child['child'], $leaf);

					array_push($kids, $child);

				}
			}

			$node['child'] = $kids;
			return $node;
		}
		
		// 变弯 下划线
		$magic = '/([*]*)[*](.*?)[*]([*]*)|([_]*)[_](.*?)[_]([_]*)|([~]*)[~](.*?)[~]([~]*)|([-]*)[-](.*?)[-]([-]*)/';

		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				array_push($kids, $this->createChild(array('text'=>$v)));

				// 内联数组
				if($re[0]){

					$match = array_shift($re[0]);

					$sign = substr($match, 0, 1);

					
					// 斜体 下划线
					switch ($sign) {
						case '*':
							$text = array_shift($re[1]).array_shift($re[2]).array_shift($re[3]);
							$type = "i";
							break;
						case '_':
							$text = array_shift($re[4]).array_shift($re[5]).array_shift($re[6]);
							$type = "i";
							break;
						case '~':
							$text = array_shift($re[7]).array_shift($re[8]).array_shift($re[9]);
							$type = "u";
							break;
						case '-':
							$text = array_shift($re[10]).array_shift($re[11]).array_shift($re[12]);
							$type = "u";
							break;

					}

					$attr = array();
					$attr['type'] = "fin";
					$attr['text'] = $text;
					$leaf = $this->createChild($attr);

					$attr['text'] = $match;
					$attr['type'] = $type;

					$child = $this->createChild($attr);

					array_push($child['child'], $leaf);

					array_push($kids, $child);

				}
			}

			$node['child'] = $kids;
			return $node;
		}
		return null;
	}

	// 生孩子
	private function createChild($attr, $text = null){
		// 模板
		$temp = array();
		$temp['display'] = "inline";
		$temp['type'] = "";
		$temp['text'] = "";
		$temp['child'] = array();

		$node = $temp;
		foreach ($attr as $k => $v) {
			$node[$k] = $v;
		}

		if($text){
			$child = $temp;
			$child['text'] = $text;

			array_push($node['child'], $child);
		}

		return $node;
	}

	// 内联代码
	private function inlineCode($node){
		
		$str = $node['text'];

		// 模板
		$temp = $node;
		$temp['type'] = "";
		$temp['display'] = "inline";
		$temp['text'] = "";

		// 匹配内联代码的魔法 双反向引号 类型一 kbd
		$magic = '/ *``(.*?)`` */';

		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				$t = $temp;
				$t['text'] = $v;

				array_push($kids, $t);

				// 内联数组
				if($re[0]){
					$kbd = $temp;
					$kbd['type'] = "kbd";
					$kbd['text'] = array_shift($re[0]);

					$child = $temp;
					// $child['text'] = $this->charToUnicode(array_shift($re[1]));
					$child['text'] = array_shift($re[1]);


					$this->unicodeToChar($child['text']);

					array_push($kbd['child'], $child);

					array_push($kids, $kbd);
				}
			}


			$node['child'] = $kids;
			return $node;
		}

		// 匹配内联代码的魔法 单反向引号 类型二 code
		$magic = '/ *`(.*?)` */';

		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				$t = $temp;
				$t['text'] = $v;

				array_push($kids, $t);

				// 内联数组
				if($re[0]){
					$code = $temp;
					$code['type'] = "code";
					$code['text'] = array_shift($re[0]);

					$child = $temp;
					// $child['text'] = $this->charToUnicode(array_shift($re[1]));
					$child['text'] = array_shift($re[1]);

					array_push($code['child'], $child);

					array_push($kids, $code);
				}
			}

			$node['child'] = $kids;
			return $node;
		}

		return null;
	}

	// 自动链接 自动邮箱
	private function autoLink($node){
		$str = $node['text'];

		// 模板
		$temp = $node;
		$temp['type'] = "";
		$temp['display'] = "inline";
		$temp['text'] = "";

		// 邮箱识别魔法
		$magic = '/<[ ]*(.+?@.+?\..+?)[ ]*>/';
		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				$t = $temp;
				$t['text'] = $v;

				array_push($kids, $t);

				// 邮箱数组
				if($re[0]){
					$mail = $temp;
					$mail['type'] = "a";
					$mail['text'] = array_shift($re[0]);

					$address = $this->charToUnicode(array_shift($re[1]));
					$mail['url'] = $this->charToUnicode("mailto:").$address;

					$child = $temp;
					$child['text'] = $address;

					array_push($mail['child'], $child);

					array_push($kids, $mail);
				}
			}

			$node['child'] = $kids;

			return $node;
		}

		// 网址识别魔法
		$magic = '/<[ ]*((http|https)*:{0,1}\/\/.*?)[ ]*>/';
		if(preg_match_all($magic, $str, $re)){
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				$t = $temp;
				$t['text'] = $v;

				array_push($kids, $t);

				// 网址数组
				if($re[0]){
					$link = $temp;
					$link['type'] = "a";
					$link['text'] = array_shift($re[0]);

					$link['url'] = array_shift($re[1]);

					$child = $temp;
					$child['text'] = $link['url'];

					array_push($link['child'], $child);

					array_push($kids, $link);

				}
			}

			$node['child'] = $kids;

			return $node;
		}

		
		return null;		
	}

	// 内联链接
	private function inlineLink($node){

		$str = $node['text'];

		// 模板
		$temp = $node;
		$temp['type'] = "";
		$temp['display'] = "inline";
		$temp['text'] = "";

		// 通过ID实现的链接魔法
		$magic = '/([ ]*)\[(.*?)\][ ]*\[(.*?)\]([ ]*)/';

		if(preg_match_all($magic, $str, $re)){

			// 魔法拆解
			$arr = preg_split($magic, $str);
			
			// 拆出一堆孩子
			$kids = array();

			foreach ($arr as $v) {
				$attr = array();
				$attr['text'] = $v;
				array_push($kids, $this->createChild($attr));

				if($space = array_shift($re[1])){
					array_push($kids, $this->createChild(array('text' => $space)));
				}

				// 构造链接数组
				if($re[0]){

					$attr = array();
					$attr['type'] = "a";
					$text = array_shift($re[0]);
					$attr['text'] = $text;

					$txt = array_shift($re[2]);
					$link = $this->createChild($attr, $txt);

					$id = array_shift($re[3]);
					if(!$id) $id = $txt;

					$id = $this->idList[$id];

					$link['url'] = "";
					$link['title'] = "";

					if($id){
						$link['url'] = $id['url'];
						$link['title'] = $id['title'];
					}

					array_push($kids, $link);
				}

				if($space = array_shift($re[4])){
					array_push($kids, $this->createChild(array('text' => $space)));
				}
			}

			$node['child'] = $kids;
			return $node;
		}

		// 就地编写的链接魔法
		$magic = '/([ ]*)\[(.*?)\][ ]*\((.*?)[ ]*(\"(.*?)\")*\)([ ]*)/';
		if(preg_match_all($magic, $str, $re)){

			// 魔法拆解
			$arr = preg_split($magic, $str);

			$kids = array();

			foreach ($arr as $v) {
				$attr = array();
				$attr['text'] = $v;
				array_push($kids, $this->createChild($attr));

				if($space = array_shift($re[1])){
					array_push($kids, $this->createChild(array('text' => $space)));
				}

				// 链接数组
				if($re[0]){

					$attr = array();
					$attr['type'] = "a";
					$text = array_shift($re[0]);
					$attr['text'] = $text;
					$attr['url'] = array_shift($re[3]);
					$attr['title'] = array_shift($re[5]);

					$txt = array_shift($re[2]);

					$link = $this->createChild($attr, $txt);

					array_push($kids, $link);
				}

				if($space = array_shift($re[6])){
					array_push($kids, $this->createChild(array('text' => $space)));
				}
			}


			$node['child'] = $kids;

			return $node;
		}

		return null;
	}

	// 处理转义
	private function inlineEscape($node){

		// 转义魔法
		$magic = '/\\\\(.)/';
		
		$str = $node['text'];
		if(preg_match($magic, $str)){
			$str = preg_replace_callback($magic, array($this, "cbEscape"), $str);
			$node['text'] = $str;
		}

		return $node;
	}

	// 转义回调
	private function cbEscape($matches){

		return $this->charToUnicode($matches[1]);
	}

	// 从unicode转回来
	// 把'&#x4f60;&#x597d;&#x4e16;&#x754c;'变回'你好世界'
	private function unicodeToChar($str){
		$line = "";

		// 用魔法提取里面的编码
		$magic = '/&#x(.*?);/';
		if(preg_match_all($magic, $str, $re)){

			while ($cur = array_shift($re[1])) {
				// 保证四位一组
				$bin = "0000".$cur;
				$bin = substr($bin, strlen($bin) - 4);

				$line .= iconv('UCS-2', 'UTF-8', pack('H4', $bin));
			}
		}

		return $line;
	}

	// 将字符转换成unicode编码
	// 比如'你好世界'会变成'&#x4f60;&#x597d;&#x4e16;&#x754c;'
	private function charToUnicode($str){
		$line = "";
		foreach(unpack('n*', mb_convert_encoding($str, "unicode", "utf-8")) as $i) {
			$line .= "&#x".dechex($i).";";
		}

		return $line;
	}

	// 传入未经处理的md树，将块级元素解析转换后返回
	private function blockTransform($tree){

		// 这个文本太长了 暂时隐藏掉
		$tree['text'] = "";

		// 记录双亲类型
		$type = $tree['type'];
		$display = $tree['display'];
		$child = $tree['child'];

		// 遍历孩子并打上标记
		foreach ($child as $k => $v) {

			// 双空格结尾 == 真·换行
			// 空字符行 == 伪·换行
			if($type == "root" || $type == "blockquote"){
				if($re = $this->flagBr($v)){ $child[$k] = $re; continue; }
			}

			// 只有root节点处理
			if($type == "root"){

				// 处理ID
				if($re = $this->parseID($v)){ $child[$k] = $re; continue; }

				// hr分割
				if($re = $this->parseHr($v)){ $child[$k] = $re; continue; }
			}

			// 非代码节点处理
			if($type != "pre" && $type != "preMark"){

				// 处理h1~h6
				if($re = $this->parseH1toH6($v)){ $child[$k] = $re; continue; }

				// 处理blockquote引用
				if($re = $this->parseBlockquote($v)){ $child[$k] = $re; continue; }

				// 处理无序或有序列表
				if($re = $this->parseList($v)){ $child[$k] = $re; continue; }

				// 处理图片
				if($re = $this->parseImage($v)){ $child[$k] = $re; continue; }

				// 处理代码块 pre
				if($re = $this->parsePre($v)){ $child[$k] = $re; continue; }
			}
		}

		// 合并同类型
		$child = $this->mergeSameType($child);

		// 递归
		foreach ($child as $k => $v) {

			if($v['child']){
				$child[$k] = $this->blockTransform($v);
			}
		}

		$tree['child'] = $child;

		return $tree;
	}

	// 合并同类型
	private function mergeSameType($list){

		// 标记终止行
		$list = $this->markP($list);

		// 双空格换行
		$list = $this->parseRealBr($list);

		// 合并代码块
		$list = $this->mergePre($list);

		// 合并blockquote
		$list = $this->mergeBlockquote($list);
		
		// 合并列表
		// 好多list看起来好混乱的说 QAQ
		$list = $this->mergeList($list);

		return $list;
	}

	private function removeFakeBr($list){
		$re = array();
		foreach ($list as $v) {
			if($v['type'] == "br" && $v['display'] == "inline") continue;
			array_push($re, $v);
		}

		return $re;
	}

	// 非换行+双换行 == P标签结束
	// 即 非换行+空行 == P标签结束
	// 否则忽略
	private function markP($list){
		$length = count($list);

		for($i = 0; $i < $length; $i++){
			$current = $list[$i];

			// 当前行不是br 也不是pMark
			if($i+1 < $length && $current['type'] != "br" && $current['type'] != "pMark"){

				$n1 = $list[$i+1];

				// 下一行是内联 br
				if($n1['display'] == "inline" && $n1['type'] == "br"){
					$list[$i+1]['type'] = "pMark";
					$i += 1;
				}
			}
		}

		return $list;
	}

	// 合并列表
	// 列表可以包含引用
	// 列表可以包含代码
	// 列表啥都能包含 QAQ
	// 甚至能容忍一个空行
	// 截止规则
	// 		遇到下一个oli或uli
	// 		在遇到pMark之后遇到未标记元素
	private function mergeList($list){

		$length = count($list);

		// 第一次循环 合并li
		for($i = 0; $i < $length; $i++){
			$current = $list[$i];

			// 遇到无序 或有序
			if($current['type'] == "uli" || $current['type'] == "oli"){
				$cur = $i + 1;
				$metPMark = false;

				while ($cur < $length) {
					$next = $list[$cur];

					// 遇到了下一个li
					if($next['type'] == "uli" || $next['type'] == "oli"){
						break;
					}

					// 遇到了pMark 标记
					if($next['type'] == "pMark"){
						$metPMark = true;
					}

					// 遇到过pMark 又遇到了未知元素或换行
					if($metPMark && ($next['type'] == "" || $next['type'] == "br")){
						break;
					}
					

					if($next['child']){
						foreach ($next['child'] as $v) {
							array_push($current['child'], $v);
						}
					}
					else {
						array_push($current['child'], $next);
					}
					$current['text'] .= "\n".$next['text'];

					$list[$cur]['type'] = "del";

					$cur++;
				}

				$list[$i] = $current;
			}
		}

		$list = $this->removeDel($list);

		// 第二次循环 将li合并到ol或者ul
		$length = count($list);
		for($i = 0; $i < $length; $i++){
			$current = $list[$i];

			if($current['type'] == "oli" || $current['type'] == "uli"){
				$cur = $i+1;
				$type = $current['type'];

				$kids = array();
				$text = $current['text'];

				$current['type'] = "li";
				array_push($kids, $current);

				while ($cur < $length) {
					$next = $list[$cur];

					if($next['type'] != $type){
						break;
					}

					$text .= "\n".$next['text'];

					$next['type'] = "li";
					array_push($kids, $next);

					$next['type'] = "del";
					$list[$cur] = $next;

					$cur++;
				}

				// 双亲数组
				$attr = array();
				$attr['display'] = "block";
				$attr['type'] = substr($type, 0, 2);
				$attr['text'] = $text;

				$parent = $this->createChild($attr);
				$parent['child'] = $kids;

				$list[$i] = $parent;
			}
		}

		return $this->removeDel($list);
	}

	// 合并Pre
	// 这里有两种情况
	// 1. 遇到pre 即用缩进标记的代码区块
	// 		遇到pre以外的元素终止
	// 2. 遇到preMark
	// 		遇到preMark终止
	private function mergePre($list){

		$length = count($list);

		for($i = 0; $i < $length; $i++){
			$current = $list[$i];

			// 遇到pre
			if($current['type'] == "pre"){
				$cur = $i + 1;
				$brCount = 0;

				while ($cur < $length) {
					$next = $list[$cur];

					// 非pre 截断
					if($next['type'] != "pre") break;

					if($next['child']){
						foreach ($next['child'] as $v) {
							array_push($current['child'], $v);
						}
					}
					else {
						array_push($current['child'], $next);
					}
					$current['text'] .= "\n".$next['text'];

					$list[$cur]['type'] = "del";

					$cur++;
				}

				$list[$i]  = $current;
			}

			// 遇到 preMark ```开头
			else if($current['type'] == "preMark"){

				$cur = $i + 1;
				while ($cur < $length) {
					$next = $list[$cur];

					// 唯独遇到preMark截断
					if($next['type'] == "preMark"){
						$next['type'] = "del";
						$list[$cur] = $next;
						break;
					}

					if($next['child']){
						foreach ($next['child'] as $v) {
							array_push($current['child'], $v);
						}
					}
					else {
						array_push($current['child'], $next);
					}
					$current['text'] .= "\n".$next['text'];

					$list[$cur]['type'] = "del";

					$cur++;
				}

				$list[$i]  = $current;
			}
		}

		return $this->removeDel($list);
	}

	// 合并blockquote
	// 遇到pMark截止
	private function mergeBlockquote($list){

		$length = count($list);

		for($i = 0; $i < $length; $i++){
			$current = $list[$i];

			// 遇到blockquote
			if($current['type'] == "blockquote"){
				$cur = $i + 1;

				while($cur < $length){

					$next = $list[$cur];

					// 统计换行 如果连续两个换行则结束合并
					if($next['type'] == "pMark"){
						break;
					}

					// 如果有孩子 将孩子并入
					if($next['child']){
						foreach ($next['child'] as $v) {
							array_push($current['child'], $v);
						}
					}
					// 没有孩子 将该节点并入孩子
					else {
						array_push($current['child'], $next);
					}
					$current['text'] .= "\n".$next['text'];

					// 标记删除该节点
					$list[$cur]['type'] = "del";

					$cur++;
				}

				$list[$i] = $current;
			}
		}

		// 删除带del标签的节点
		return $this->removeDel($list);
	}

	// 删除带del标签节点
	private function removeDel($list){
		$re = array();
		foreach ($list as $v) {
			if($v['type'] != "del") array_push($re, $v);
		}

		return $re;
	}

	// 处理图片
	private function parseImage($node){
		$magic = array(
				'/^!\[(.*?)\]\((.*?)\)/',		# ![Alt text](/path/to/img.jpg)
				'/^!\[(.*?)\]\[(.*?)\]/',		# ![Alt text][id]
				'/(.*?)[ ]+\"(.*?)\"/'			# /path/to/img.jpg "Optional title"
			);

		$str = $node['text'];
		if(preg_match($magic[0], $str, $re)){
			$node['display'] = "block";
			$node['type'] = "img";
			$node['alt'] = $re[1];

			if(preg_match($magic[2], $str, $r)){
				$node['url'] = $r[1];
				$node['title'] = $r[2];
			} else {
				$node['url'] = $re[2];
				$node['title'] = "";
			}

			return $node;
		}

		if(preg_match($magic[1], $str, $re)){
			$node['display'] = "block";
			$node['type'] = "img";
			$node['alt'] = $re[1];
			$node['url'] = "";
			$node['title'] = "";

			// 下标不一定存在 因此加上@符号隐藏Notice提示
			@$re = $this->idList[$re[2]];

			if($re){
				$node['url'] = $re['url'];
				$node['title'] = $re['title'];
			}

			return $node;
		}

		return null;
	}

	// 无序或有序列表
	private function parseList($node){

		// 无序
		$magic = '/^[+*-][ \t]+(.*?)$/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){

			$attr = array();
			$attr['display'] = "block";
			$attr['type'] = "uli";
			$attr['text'] = $re[1];

			$node = $this->createChild($attr, $re[1]);

			return $node;
		}

		// 有序
		$magic = '/^[0-9]+[.][ ]+(.*?)$/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){

			$attr = array();
			$attr['display'] = "block";
			$attr['type'] = "oli";
			$attr['text'] = $re[1];

			$node = $this->createChild($attr, $re[1]);

			return $node;
		}

		return null;
	}

	// 代码块
	private function parsePre($node){
		$magic = '/^[ ]{4}(.*?)$|^[ ]*\t+(.*?) *$/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){

			$child = $node;
			$child['text'] = $re[count($re) - 1];

			$node['display'] = "block";
			$node['type'] = "pre";
			$node['child'][] = $child;

			return $node;
		}

		$magic = '/^`{3,}[ ]*(.*)[ ]*/';

		if(preg_match($magic, $str, $re)){

			$node['display'] = "block";
			$node['type'] = "preMark";
			$node['language'] = $re[1];

			return $node;
		}

		return null;
	}

	// blockquote
	// > 之后的内容为引用
	// 引用可以嵌套引用 引用可以包含代码块
	private function parseBlockquote($node){
		$magic = '/^>[ ](.*?)$/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){

			$child = $node;
			$child['text'] = $re[1];

			$node['display'] = "block";
			$node['type'] = "blockquote";
			$node['child'][] = $child;

			return $node;
		}

		return null;
	}

	// h1~h6
	private function parseH1toH6($node){
		$magic = '/^(\#{1,6})[ ]*(.+?)[ ]*\#*$/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){
			
			$child = $node;
			$child['text'] = $re[2];

			$node['display'] = "block";
			$node['type'] = "h".strlen($re[1]);
			$node['child'][] = $child;

			return $node;
		}

		return null;
	}

	// hr分割线
	private function parseHr($node){
		$magic = '/^[ ]{0,3}[-*=]{3,}$|^[ ]{0,3}[-*=]+[ ]*[-*=]+[ ]*[-*=]+[ ]*$/';
		
		$str = $node['text'];
		if(preg_match($magic, $str, $re)){
			$node['display'] = "block";
			$node['type'] = "hr";

			return $node;
		}

		return null;
	}


	// 图片ID 链接ID 锚点 脚注
	// 骗你的啦 根本没做锚点和脚注的设定！
	private function parseID($node){
		$magic = '/\[(.*?)\]:[ ]*(.*?)[ ]*\"(.*?)\"/';

		$str = $node['text'];
		if(preg_match($magic, $str, $re)){

			$node['display'] = "none";
			// $node['type'] = "id";
			$node['type'] = "del";
			$node['id'] = $re[1];
			$node['url'] = $re[2];
			$node['title'] = $re[3];

			$arr = array();
			$arr['url'] = $re[2];
			$arr['title'] = $re[3];

			$this->idList[$re[1]] = $arr;

			return $node;
		}

		$magic = '/\[(.*?)\]:[ ]*(.*?)$/';
		if(preg_match($magic, $str, $re)){
			$node['display'] = "none";
			// $node['type'] = "id";
			$node['type'] = "del";
			$node['id'] = $re[1];
			$node['url'] = $re[2];
			$node['title'] = "";

			$arr = array();
			$arr['url'] = $re[2];
			$arr['title'] = "";

			$this->idList[$re[1]] = $arr;

			return $node;
		}

		return null;

	}

	// 真·换行转换
	private function parseRealBr($list){

		$return = array();

		// 双空格换行处理
		$magic = '/^(.+?)[ ]{2,}$/';
		foreach ($list as $v) {
			$str = $v['text'];

			if(preg_match($magic, $str, $re)){

				$child = $this->createChild(array('text' => $re[1]));
				array_push($return, $child);

				$child = $this->createChild(array('text' => "<br>", 'type' => "br", 'display' => "block"));
				array_push($return, $child);
			} else {
				array_push($return, $v);
			}
		}

		return $return;
	}

	// 空行标记
	private function flagBr($node){

		$return = null;

		if(!$node['text']){
			$node['type'] = "br";
			$return = $node;
		}

		return $return;
	}

	// 把经过一系列转换的markdown字符串转换成数组
	// 按照换行符打散
	// 返回一个数组
	private function parseArr($str){
		$temp = array();
		$temp['display'] = "inline";
		$temp['type'] = "";
		$temp['text'] = "";
		$temp['child'] = array();

		$arr = explode("\n", $str);

		foreach ($arr as $k => $v) {
			$arr[$k] = $temp;
			$arr[$k]['text'] = $v;
		}

		$root = $temp;
		$root['type'] = "root";
		$root['display'] = "block";
		$root['text'] = $str;
		$root['child'] = $arr;

		return $root;
	}

	// 回调函数
	// 作用是计算\t的数量，转换成对应长度的空格
	// 根据正则匹配结果返回替换文本
	private function cbDetab($matches){
		$re = $matches[0];

		// 拆成数组
		$arr = explode("\t", $re);

		$now = $arr[0];
		unset($arr[0]);
		foreach ($arr as $v) {
			$now .= str_repeat(" ", $this->tabWidth).$v;
		}

		return $now;
	}

}

