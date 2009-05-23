<?php
/*
project.php is used as a quicker deployment solution for smaller sites
this file has priority over what is found in the assets folder, so if you
want to just forget about the asset folder and go to town using project.php
               have at it
*/
class project{
  public function project(){
	# when underminding the assets folder this becomes a great spot to do initialization type happiness
	# for example checking if user is logged in, or intercepting the $_POST info
  }
  
  public function about_ingred(){
	global $ingred;
	$ingred->design->title = 'ingred';
    $ingred->xhtml->body .= '<h2>What <em>is</em> ingred?</h2>
   <p>ingred is a modest RESTful deployment written in PHP that intends to break down the barriers of markup and allow for flexibility and scalability.</p>';

  }
}

?>