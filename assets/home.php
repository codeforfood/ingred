<?

//$this->cfg['project.name'] = 'ingred';
//$this->cfg['design.name'] = 'ingred'; # setup master template /conf/tpl/_name.tpl

# anything in $this->xhtml->body will end up in $this->xhtml->design 
$this->xhtml->body .= $this->io->read($this->cfg['dir.tpl'] . 'home.tpl');

#todo: fix html
#todo: fix debug html
#todo: redo navigation arrow

?>