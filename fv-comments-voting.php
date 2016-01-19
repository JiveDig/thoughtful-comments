<?php

class FV_Comments_Voting {
  
  var $aCache = array();
       
  function __construct() {
    add_filter( 'comments_array', array($this,'prefetch') );
    
    add_action( 'comment_text', array($this,'buttons') );
    
    add_action( 'wp_head', array($this,'javascript') );
    
    add_action( 'wp_ajax_fv_tc_voting', array($this,'ajax') );
    add_action( 'wp_ajax_nopriv_fv_tc_voting', array($this,'ajax') );
  }
  
  
  function ajax() {
    global $wpdb;
    $table_name = FV_Comments_Voting::get_table_name();
    
    // Dati controllo
    $postid = intval( $_POST['postid'] );
    $ratetype = $_POST['ratetype'];
    $user_id = get_current_user_id();
    $identifier = $user_id ? $user_id : $_SERVER['REMOTE_ADDR'];
    
    // Controllo presenza record nel db
    self::checkRow($postid);
    
    // Dati db
    $likes = self::getLikeCount($postid);
    $dislikes = self::getDislikeCount($postid);
    $likes_ov = self::getLikeIpList($postid);
    $dislikes_ov = self::getDislikeIpList($postid);
    
    switch( $ratetype ) :
      case 'like' :
        if( !in_array($identifier,$likes_ov) ) :
          $likes++;
          $likes_ov[] = $identifier;
          if( in_array($identifier,$dislikes_ov) ) :
            $valuekey = array_search($identifier,$dislikes_ov);
            unset( $dislikes_ov[$valuekey] );
            $dislikes--;
          endif;
          $wpdb->update( 
            $table_name, 
            array( 
              'rate_like_value' => $likes,
              'rate_dislike_value' => $dislikes,
              'rate_like_ip' => json_encode($likes_ov),
              'rate_dislike_ip' => json_encode($dislikes_ov)
            ), 
            array( 'comment_id' => $postid )
          );
        endif;
        if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'compact' ) { echo $likes - $dislikes; } else { echo $likes.'#'.$dislikes; }
        break;
      
      case 'dislike' :
        if( !in_array($identifier,$dislikes_ov) ) :
          $dislikes++;
          $dislikes_ov[] = $identifier;
          if( in_array($identifier,$likes_ov) ) :
            $valuekey = array_search($identifier,$likes_ov);
            unset( $likes_ov[$valuekey] );
            $likes--;
          endif;

          $wpdb->update( 
            $table_name, 
            array( 
              'rate_like_value' => $likes,
              'rate_dislike_value' => $dislikes,
              'rate_like_ip' => json_encode($likes_ov),
              'rate_dislike_ip' => json_encode($dislikes_ov)
            ), 
            array( 'comment_id' => $postid )
          );
        endif;
        if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'compact' ) { echo $likes - $dislikes; } else { echo $likes.'#'.$dislikes; }
        break;
      
    endswitch;
  
    die();    
  }
  
  
  function buttons() {
    $options = get_option('thoughtful_comments');
    
    global $comment;
	  $comment_id = get_comment_ID();
    ?>
    <div class="fv_tc_voting_box">
      <div class="fv_tc_voting fv_tc_voting_like" data-postid="<?php echo $comment_id; ?>" data-ratetype="like">
        <img src="<?php echo plugins_url( 'images/up.png', __FILE__ ); ?>" />
        <?php if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'splitted' ) : ?>
          <span><?php echo $this->getLikeCount( $comment_id ); ?></span>
        <?php endif; ?>
      </div>
      <div class="fv_tc_voting fv_tc_voting_dislike" data-postid="<?php echo $comment_id; ?>" data-ratetype="dislike" >
        <img src="<?php echo plugins_url( 'images/down.png', __FILE__ ); ?>" />
        <?php if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'splitted' ) : ?>
          <span><?php echo $this->getDislikeCount($comment_id); ?></span>
        <?php endif; ?>
      </div>
      <?php
      if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'compact' ) {
        $this->getLikeDislikeCountDiffHtml();
      }
      ?>
      <div style="clear:left;"></div>
    </div>
    <?php
  }
  
  
  public function checkRow( $comment_id = NULL) {
    global $wpdb;
    if( empty($comment_id) )	$comment_id = get_comment_ID();
    
    $table_name = FV_Comments_Voting::get_table_name();
    $row = $wpdb->get_row("SELECT * FROM $table_name WHERE comment_id = ".$comment_id);
    
    if(!$row) {
      return $wpdb->insert($table_name,array( 
          'comment_id' => $comment_id
      ));
    }
    
    return true;
  }

  
  public static function getLikeCount( $comment_id ) {      
    global $fvcr;
    if( isset($fvcr->aCache) && isset($fvcr->aCache[$comment_id]) ) {
      return $fvcr->aCache[$comment_id]->rate_like_value;
    }   
          
    global $wpdb;
    $table_name = FV_Comments_Voting::get_table_name();
    return $wpdb->get_var("SELECT rate_like_value FROM $table_name WHERE comment_id = ".$comment_id);   
  }
  
  
  public static function getDislikeCount( $comment_id ) {
    global $fvcr;
    if( isset($fvcr->aCache) && isset($fvcr->aCache[$comment_id]) ) {
      return $fvcr->aCache[$comment_id]->rate_dislike_value;
    }    
          
    global $wpdb;
    $table_name = FV_Comments_Voting::get_table_name();
    return $wpdb->get_var("SELECT rate_dislike_value FROM $table_name WHERE comment_id = ".$comment_id);                    
  }
  
  
  public static function getLikeDislikeCountDiffHtml() {
    $diff = intval(self::getLikeCount() - self::getDislikeCount());
    if($diff > 0) : ?>
      <div class="fv_tc_voting_count fv_tc_voting_count_positive">
        <?php echo $diff; ?>
      </div>
    <?php elseif( $diff < 0 ) : ?>
      <div class="fv_tc_voting_count fv_tc_voting_count_negative">
        <?php echo $diff; ?>
      </div>
    <?php else : ?>
      <div class="fv_tc_voting_count fv_tc_voting_count_neutral">
        <?php echo $diff; ?>
      </div>
    <?php endif;
  }
  
  
  public static function getLikeIpList( $comment_id = NULL ) {
    global $wpdb;
    if(empty($comment_id))	$comment_id = get_comment_ID();
    
    $table_name = FV_Comments_Voting::get_table_name();
    $ips = $wpdb->get_var("SELECT rate_like_ip FROM $table_name WHERE comment_id = ".$comment_id);
    return $ips ? (array) json_decode($ips,true) : array();
  }
  
  public static function getDislikeIpList($post_id = NULL ) {
    global $wpdb;
    if(empty($comment_id))	$comment_id = get_comment_ID();
    
    $table_name = FV_Comments_Voting::get_table_name();
    $ips = $wpdb->get_var("SELECT rate_dislike_ip FROM $table_name WHERE comment_id = ".$comment_id);            
    return $ips ? (array) json_decode($ips,true) : array();
  }  

  
  static function get_table_name() {
    global $wpdb;
    return $wpdb->prefix.'commentvoting_fvtc';
  }  
  
  
  static function install() {
    global $wpdb;
    $table_name = FV_Comments_Voting::get_table_name();
    $wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
        `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `comment_id` INT( 11 ) NOT NULL ,
        `rate_like_value` INT( 11 ) NOT NULL DEFAULT  '0' ,
        `rate_dislike_value` INT( 11 ) NOT NULL DEFAULT  '0' ,
        `rate_like_ip` LONGTEXT NOT NULL ,
        `rate_dislike_ip` LONGTEXT NOT NULL
        ) ENGINE = MYISAM;"); //  todo: add key, post_id
  }
  
  
  function javascript() { //  todo: move into main js file
    if(!is_admin()) : ?>
    <script type="text/javascript">
      //<![CDATA[
      jQuery(document).ready(function() {
        jQuery('div.fv_tc_voting').each(function() {
          jQuery(this).click(function() {
            var thisBtn = jQuery(this);
            jQuery('div.fv_tc_voting').fadeTo('fast',0.5);
            if(!thisBtn.hasClass('in_action')) {
              jQuery('div.fv_tc_voting').addClass('in_action');
              jQuery.post(
                "<?php bloginfo('url'); ?>/wp-admin/admin-ajax.php",
                { 
                  'action' : 'fv_tc_voting',
                  'postid' : parseInt(thisBtn.data('postid')),
                  'ratetype' : thisBtn.data('ratetype') 
                }, 
                function(response) {  //  todo: use wp_localize_script
                  <?php if( isset($options['voting_display_type']) && $options['voting_display_type'] == 'compact' ) : ?>
                    var thisCount = thisBtn.parent().find('div.fv_tc_voting_count');
                    thisCount
                        .removeClass('fv_tc_voting_count_positive')
                        .removeClass('fv_tc_voting_count_negative')
                        .removeClass('fv_tc_voting_count_neutral');
                    if(parseInt(response) == 0) thisCount.addClass('fv_tc_voting_count_neutral');
                    if(parseInt(response) > 0) thisCount.addClass('fv_tc_voting_count_positive');
                    if(parseInt(response) < 0) thisCount.addClass('fv_tc_voting_count_negative');
                    thisCount.empty().text('' + parseInt(response) + '');
                  <?php else : ?>
                    var newval = response.split('#');                              
                    thisBtn.parent().find('div.fv_tc_voting_like > span').empty().text('' + parseInt(newval[0]) + '');
                    thisBtn.parent().find('div.fv_tc_voting_dislike > span').empty().text('' + parseInt(newval[1]) + '');
                  <?php endif; ?>
                  jQuery('div.fv_tc_voting').fadeTo('fast',1);
                  jQuery('div.fv_tc_voting').removeClass('in_action');
                }
              );  
            }                 
          });
        });
      });
      //]]>
    </script>
    <?php endif;    
  }
  
  
  function prefetch( $comments ) {
    $aCommentIDs = wp_list_pluck( $comments, 'comment_ID' );
    $sCommentIDs = implode( ',', $aCommentIDs );
    
    global $wpdb;
    $table_name = FV_Comments_Voting::get_table_name();
    $this->aCache = $wpdb->get_results("SELECT comment_id, rate_like_value, rate_dislike_value FROM $table_name WHERE comment_id IN (".$sCommentIDs.")", OBJECT_K );
    
    foreach( $aCommentIDs AS $id ) {
      if( !isset($this->aCache[$id]) ) $this->aCache[$id] = 0;
    }
    
    return $comments;    
  }
  
  
}
$fvcr = new FV_Comments_Voting;

register_activation_hook( __FILE__, array( 'FV_Comments_Voting', 'install' ) ); //  todo: will this ever trigger?
