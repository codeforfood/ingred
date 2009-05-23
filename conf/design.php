<?
/*
@PROJECT	ingred
@FILE		design.php
@VERSION	v0.3 - design release
@AUTHOR		Mike Garegnani
--------------------------------
*/

class design{
	public $tpl = null;
	
	public $title = null;
	public $top = null;
	public $content = null;
	public $bottom = null;
	public $nav = array();
	# $nav[0]['caption'] = 'Home';
	# $nav[0]['class'] = 'selected';
	# $nav[0]['uri'] = '/';

	function design(){ 
		global $ingred;
		$this->tpl = new tpl();
	}
	
	private function _add_nav($id, $caption, $uri, $class=null){
		$this->nav[$id]['caption'] = $caption;
		$this->nav[$id]['uri'] = $uri;
		$this->nav[$id]['class'] = $class;
	}
	
	private function _delete_nav($id){
		unset($this->nav[$id]);
	}

	private function _load_tpl(){
		global $ingred;
		$this->tpl->buffer = $ingred->io->read_tpl($ingred->cfg('dir.tpl').'_'.$ingred->cfg('design.name'));	
	}

	private function _navigation(){
		global $ingred;

		# todo: look at uri to determine whether a "here" class should be appended
		$tmp = '<ul>';
		$count = count($this->nav);
		for ($i=0;$count>$i;$i++){
			if (strtolower(substr($this->nav[$i]['uri'], 1)) == strtolower($ingred->vals['http.uri'][0])) $this->nav[$i]['class'] = 'hover';
			
			if ($ingred->vals['http.uri'][0] == $ingred->cfg('project.default.asset') && $this->nav[$i]['uri'] == '/') $this->nav[$i]['class'] = 'hover';

			$tmp .= '<li><a href="'.$ingred->vals['project.url'].$this->nav[$i]['uri'] .'"';
			if (!empty($this->nav[$i]['class'])) $tmp .= ' class="'.$this->nav[$i]['class'].'"';
			$tmp .= '>';
			$tmp .= ucwords($this->nav[$i]['caption']).'</a></li>';
		}
		$tmp .= '</ul>';
		
		return $tmp;
	}
	
	function blog(){
		global $ingred;
		$this->_load_tpl();
		$ingred->xhtml->title = 'blog asset - ';
		//$ingred->xhtml->body = $ingred->
	}
	
	function ingred(){
		global $ingred;
		$this->_load_tpl();
		$ingred->xhtml->title = $ingred->cfg('project.name') . ' - ' . $ingred->vals['http.uri'][0];
		$ingred->xhtml->build_css_object($ingred->cfg('design.name'), $ingred->cfg('public.dir.css') . '/ingred.css');
		
		#$this->_add_nav(0, 'home', '/');
		#$this->_add_nav(1, 'docementatin', '/wiki');
		#$this->_add_nav(2, 'download', '/download');
		#$this->_add_nav(3, 'contact', '/contact');
		$nav =  $this->_navigation();
		$this->top = $nav;
		$this->bottom = $nav;
		if (empty($this->title))$this->title = $ingred->cfg('project.name');
		unset($nav);

		$this->tpl->replace = array(
								'$top' => $this->top,
								'$content' => $ingred->xhtml->body,
								'$bottom' =>$this->bottom,
								'$title' => $this->title);

		$ingred->xhtml->body = $this->tpl->commit();
	}
}
?>