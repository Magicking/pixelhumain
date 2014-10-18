<?php
/**
 * [actionAddWatcher 
 * create or update a user account
 * if the email doesn't exist creates a new citizens with corresponding data 
 * else simply adds the watcher app the users profile ]
 * @return [json] 
 */
class SaveUserAction extends CAction
{
    public function run()
    {
       $email = $_POST["email"];
        if( Yii::app()->request->isAjaxRequest )
        {
            //if exists login else create the new user
            $pwd = (isset($_POST["pwd"])) ? $_POST["pwd"] : null ;
            $res = Citoyen::register( $email, $pwd);
            if($user = PHDB::findOne(PHType::TYPE_CITOYEN,array( "email" => $email ) ))
            {
                //udate the new app specific fields
                $newInfos = array();
                if( isset($_POST['cp']) )
                    $newInfos['cp'] = $_POST['cp'];
                if( isset($_POST['name']) ){
                    $newInfos['name'] = $_POST['name'];
                    Yii::app()->session["user"] = array("name"=>$_POST['name']); 
                }
                if( isset($_POST['city']) )
                    $newInfos['city'] = $_POST['city'];
                if( isset($_POST['tags']) )
                    $newInfos['tags'] = explode(",",$_POST['tags']);
                if(isset($_POST['cp']))
                    {
                         $newInfos["cp"] = $_POST['cp'];
                         $newInfos["address"] = array(
                           "@type"=>"PostalAddress",
                           "postalCode"=> $_POST['cp']);
                    }
                if(isset($_POST['country']))
                       $newInfos["address"]["addressLocality"]= $_POST['country'];

                if( isset($_POST['tags']) )
                {
                  $tagsList = PHDB::findOne( PHType::TYPE_LISTS,array("name"=>"tags"), array('list'));
                  foreach( explode(",", $_POST['tags']) as $tag)
                  {
                    if(!in_array($tag, $tagsList['list']))
                      PHDB::update( PHType::TYPE_LISTS,array("name"=>"tags"), array('$push' => array("list"=>$tag)));
                  }
                  $newInfos["tags"] = $_POST['tags'];
                }

                //specific application routines
                if( isset( $_POST["app"] ) )
                {
                    $appKey = $_POST["app"];
                    //when registration is done for an application it must be registered
                	$newInfos['applications'] = array( $appKey => array( "usertype"=> (isset($_POST['type']) ) ? $_POST['type']:$_POST['app']  ));

                	$app = PHDB::findOne(PHType::TYPE_APPLICATIONS,array( "key"=> $appKey ) );
                    //check for application specifics defined in DBs application entry
                	if( isset( $app["registration"] ))
                        if( $app["registration"] == "mustBeConfirmed" )
                		      $newInfos['applications'][$appKey]["registrationConfirmed"] = false;
                        else if( $app["registration"] == "mailValidation" )
                        {
                            Yii::app()->session["userId"] = $user["_id"]; 
                            Yii::app()->session["userEmail"] = null;
                            
                            //send validation mail
                            //TODO : make emails as cron jobs
                            $title = $app["name"];
                            $logo = ( isset($app["logo"]) ) ? $this->module->assetsUrl.$app["logo"] : Yii::app()->getRequest()->getBaseUrl(true).'/images/logo/logo144.png';
                               
                            Mail::send(array("tpl"=>'validation',
                                             "subject" => 'Confirmer votre compte '.$title,
                                             "from"=>Yii::app()->params['adminEmail'],
                                             "to" => $email,
                                             "tplParams" => array( "user"  => $user["_id"] ,
                                                                     "title" => $title ,
                                                                     "logo"  => $logo,
                                                )));
                        }
                }

                PHDB::update(PHType::TYPE_CITOYEN,
                            array("email" => $email), 
                            array('$set' => $newInfos ) 
                            );
            }
        } else
            $res = array('result' => false , 'msg'=>'something somewhere went terribly wrong');
            
        Rest::json($res);  
        Yii::app()->end();
    }
}