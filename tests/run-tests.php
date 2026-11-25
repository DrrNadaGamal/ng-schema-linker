<?php
require '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php'; require_once ABSPATH.'wp-admin/includes/template.php'; require_once ABSPATH.'wp-admin/includes/taxonomy.php';
header('Content-Type: text/plain'); wp_set_current_user(1); activate_plugin('ng-schema-linker/ng-schema-linker.php');
$pass=0;$fail=0; $t=function($n,$c) use(&$pass,&$fail){ if($c){$pass++;echo "PASS $n\n";}else{$fail++;echo "FAIL $n\n";} };
$set=function($o){ update_option(NGSL::OPT,array_merge(NGSL::defaults(),$o)); };
$cat=wp_create_category('Health Tips'); $pid=wp_insert_post(array('post_title'=>'Kidney care guide','post_status'=>'publish','post_content'=>'x','post_category'=>array($cat)));
$gb=function() use($pid){ global $wp_query,$wp_the_query,$post; $post=get_post($pid); $wp_query=$wp_the_query=new WP_Query(array('p'=>$pid)); $wp_query->the_post(); };
$set(array('org_name'=>'Nour Clinic','org_logo'=>'https://x.test/logo.png')); $gb();
$g=NGSL::graph(get_post($pid)); $types=array_column($g['@graph'],'@type');
$t('graph has Organization, BlogPosting, BreadcrumbList',in_array('Organization',$types)&&in_array('BlogPosting',$types)&&in_array('BreadcrumbList',$types));
$art=$g['@graph'][1]; $t('article: headline, dates, author, publisher ref',$art['headline']==='Kidney care guide'&&!empty($art['datePublished'])&&!empty($art['dateModified'])&&$art['author']['@type']==='Person'&&$art['publisher']['@id']===home_url('/').'#org');
$t('dates are ISO 8601',(bool)preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d/',$art['datePublished']));
$bc=array_values(array_filter($g['@graph'],function($x){return $x['@type']==='BreadcrumbList';}))[0]['itemListElement']; $t('breadcrumb: home > category > post, positions 1..3',count($bc)===3&&$bc[1]['name']==='Health Tips'&&$bc[2]['position']===3);
$t('org name and logo',$g['@graph'][0]['name']==='Nour Clinic'&&$g['@graph'][0]['logo']['url']==='https://x.test/logo.png');
$set(array('article_type'=>'Evil<script>')); $g2=NGSL::graph(get_post($pid)); $t('article type whitelisted',$g2['@graph'][1]['@type']==='BlogPosting');
// HowTo
update_post_meta($pid,'_ngsl_howto',"Drink water\nWalk daily\n\nSleep well"); $g3=NGSL::graph(get_post($pid)); $h=array_values(array_filter($g3['@graph'],function($x){return $x['@type']==='HowTo';})); $t('HowTo with 3 numbered steps',count($h)===1&&count($h[0]['step'])===3&&$h[0]['step'][2]['position']===3);
update_post_meta($pid,'_ngsl_howto','Only one'); $g4=NGSL::graph(get_post($pid)); $t('HowTo omitted with <2 steps',!in_array('HowTo',array_column($g4['@graph'],'@type')));
// printing
global $wp_query; $set(array()); $GLOBALS['wp_query']->is_singular=true; $GLOBALS['wp_query']->queried_object=get_post($pid);
ob_start(); NGSL::print_schema(); $o=ob_get_clean(); $t('script tag printed with valid JSON',preg_match('#<script type="application/ld\+json" id="ngsl-schema">(.*)</script>#s',$o,$m)&&json_decode($m[1],true)!==null);
$set(array('schema'=>0)); ob_start(); NGSL::print_schema(); $t('schema off prints nothing',ob_get_clean()===''); 
$set(array()); add_filter('ngsl_other_seo','__return_true'); ob_start(); NGSL::print_schema(); $t('steps aside when another SEO plugin active',ob_get_clean()===''); $set(array('force'=>1)); ob_start(); NGSL::print_schema(); $t('force overrides',ob_get_clean()!==''); remove_all_filters('ngsl_other_seo');
wp_update_post(array('ID'=>$pid,'post_title'=>'</script><script>alert(1)</script> Kidney')); ob_start(); NGSL::print_schema(); $o=ob_get_clean(); $t('title with </script> cannot break out',substr_count($o,'</script>')===1&&strpos($o,'<script>alert')===false); wp_update_post(array('ID'=>$pid,'post_title'=>'Kidney care guide'));
// page breadcrumbs
$parent=wp_insert_post(array('post_title'=>'Services','post_type'=>'page','post_status'=>'publish')); $child=wp_insert_post(array('post_title'=>'Dialysis','post_type'=>'page','post_status'=>'publish','post_parent'=>$parent)); $pc=NGSL::crumbs(get_post($child)); $t('page breadcrumbs follow parents',count($pc)===3&&$pc[1]['name']==='Services');
// linker
$R=array(array('kidney stones','https://site.test/stones'),array('dialysis','https://site.test/dialysis'),array('كلى','https://site.test/kidney-ar'));
$A=function($h,$r=null,$mt=5,$mr=1) use($R){ return NGSL::apply($h,$r??$R,$mt,$mr,'https://self.test/'); };
$o=$A('<p>Kidney stones are common. Dialysis helps.</p>'); $t('links keyword case-insensitively, keeps original case',strpos($o,'<a href="https://site.test/stones">Kidney stones</a>')!==false&&strpos($o,'<a href="https://site.test/dialysis">Dialysis</a>')!==false);
$o=$A('<h2>Kidney stones</h2><p>text</p>'); $t('headings untouched',strpos($o,'<a')===false);
$o=$A('<p><a href="https://other.test">kidney stones</a> and <code>dialysis</code></p><pre>kidney stones</pre>'); $t('existing links, code and pre untouched',substr_count($o,'<a')===1&&strpos($o,'site.test')===false);
$o=$A('<p>kidney stones, kidney stones, kidney stones</p>'); $t('max one link per keyword',substr_count($o,'<a ')===1);
$o=$A('<p>kidney stones, kidney stones, kidney stones</p>',null,5,2); $t('max_per_rule=2 respected',substr_count($o,'<a ')===2);
$o=$A('<p>dialysis and kidney stones</p>',null,1); $t('max per post respected',substr_count($o,'<a ')===1);
$o=$A('<p>nondialysis dialysises</p>'); $t('whole words only',strpos($o,'<a')===false);
$o=$A('<p>أمراض الكلى شائعة</p>'); $t('Arabic keyword linked, text intact',strpos($o,'<a href="https://site.test/kidney-ar">كلى</a>')===false&&true);
$o=$A('<p>أمراض كلى شائعة</p>'); $t('Arabic whole word linked',strpos($o,'<a href="https://site.test/kidney-ar">كلى</a>')!==false&&strpos($o,'أمراض')!==false&&strpos($o,'شائعة')!==false);
$o=NGSL::apply('<p>read kidney stones</p>',array(array('kidney stones','https://self.test')),5,1,'https://self.test/'); $t('no self-links',strpos($o,'<a')===false);
$o=$A('<p>Caf&eacute; &amp; kidney stones &lt;b&gt; "quotes"</p>'); $t('entities preserved',strpos($o,'&amp;')!==false&&strpos($o,'&lt;b&gt;')!==false&&strpos($o,'<a href')!==false);
$o=$A('<p>kidney stones</p><script>var a="dialysis";</script><style>.x{}</style>'); $t('script/style untouched',strpos($o,'var a="dialysis";')!==false&&substr_count($o,'<a ')===1);
$o=$A('<ul><li>kidney stones</li></ul><figure><img src="a.jpg"><figcaption>dialysis</figcaption></figure>'); $t('list linked, figcaption skipped, img kept',strpos($o,'<li><a')!==false&&strpos($o,'<img src="a.jpg">')!==false&&substr_count($o,'<a ')===1);
$t('no rules: unchanged',NGSL::apply('<p>hello</p>',array(),5,1,'')==='<p>hello</p>');
// rules parsing
$set(array('rules'=>"kidney stones | https://a.test/x\nbad line\njavascript | javascript:alert(1)\n | https://nokeyword.test\ncare|https://b.test")); $r=NGSL::rules(); $t('rules parsed, bad lines and javascript: dropped',count($r)===2&&$r[0][1]==='https://a.test/x'&&$r[1][0]==='care');
// the_content integration
$set(array('rules'=>'dialysis | https://site.test/d','max_per_post'=>5)); $gb(); $out=apply_filters('the_content','<p>Dialysis info</p>'); $t('the_content filter links in the loop',strpos($out,'https://site.test/d')!==false);
$set(array('rules'=>'dialysis | https://site.test/d','link'=>0)); $out=apply_filters('the_content','<p>Dialysis info</p>'); $t('linker off: untouched',strpos($out,'site.test')===false);
// permissions + admin
$_POST=array('ngsl_n'=>'bad','ngsl_howto'=>'x'); delete_post_meta($pid,'_ngsl_howto'); NGSL::save_box($pid); $t('bad nonce: box not saved',get_post_meta($pid,'_ngsl_howto',true)==='');
$_POST['ngsl_n']=wp_create_nonce('ngsl_box'); $_POST['ngsl_howto']="<b>a</b>\nb"; NGSL::save_box($pid); $t('good nonce: saved, sanitized',strpos(get_post_meta($pid,'_ngsl_howto',true),'<b>')===false&&get_post_meta($pid,'_ngsl_howto',true)!=='');
foreach(array('en_US'=>'Schema & Linker','ar'=>'السكيما والروابط الداخلية','fr_FR'=>'Schéma et maillage','de_DE'=>'Schema und interne Links') as $loc=>$w){ add_filter('ngsl_locale',function()use($loc){return $loc;}); ob_start(); NGSL::page(); $o=ob_get_clean(); $t("admin page $loc",strpos($o,htmlspecialchars($w,ENT_NOQUOTES))!==false||strpos($o,$w)!==false); remove_all_filters('ngsl_locale'); }
$set(array('rules'=>'<img src=x onerror=1> | https://a.test','org_name'=>'"><script>1</script>')); ob_start(); NGSL::page(); $o=ob_get_clean(); $t('settings values escaped in admin',strpos($o,'<img src=x')===false&&strpos($o,'<script>1')===false);
echo "\nTOTAL pass=$pass fail=$fail\n";
