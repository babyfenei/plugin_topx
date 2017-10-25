<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./include/global_arrays.php");

set_default_action();

$poller_interval = read_config_option("poller_interval");
$selectedTheme = get_selected_theme();


$ar_age = array ("quarter" => "15 minutes", "hour" => "Last Hour", "day" => "Last Day", "week" => "Last Week", "month" => "Last Month"); 
$ar_topx = array ("1" => "Top 1", "3" => "Top 3", "5" => "Top 5", "10" => "Top 10", "0" => "All"); 
$ar_sort = array ("normal" => "normal", "reverse" => "reverse"); 


/* if the user pushed the 'clear' button */
if (get_request_var('clear_x')) {
    unset($_SESSION["age"]);
    unset($_SESSION["topx"]);
    unset($_SESSION["sort"]);
}

//if ( isset_request_var ('age') && array_key_exists (get_request_var ('age'), $ar_age))
if ( isset_request_var ('age') )
    $_SESSION["age"] = get_request_var ('age');
if (!isset($_SESSION["age"]))
    $_SESSION["age"] = "quarter";

$_SESSION['topx'] = get_filter_request_var('topx', FILTER_VALIDATE_INT, $options['default'] = 5);

if ( isset_request_var ('sort') && array_key_exists (get_request_var ('sort'), $ar_sort)) 
    $_SESSION["sort"] = get_request_var ('sort');
if (!isset($_SESSION["sort"]) || !is_string($_SESSION["sort"]))
    $_SESSION["sort"] = "normal";


general_header();

print "<link type='text/css' href='" . $config["url_path"] . "plugins/topx/themes/common.css' rel='stylesheet'>\n";
print "<link type='text/css' href='" . $config["url_path"] . "plugins/topx/themes/" . $selectedTheme . ".css' rel='stylesheet'>\n";


?>

<script type="text/javascript">
<!--

function applyViewAgeFilterChange(objForm) {
	strURL = '?age=' + objForm.age.value;
	strURL = strURL + '&topx=' + objForm.topx.value;
	strURL = strURL + '&sort=' + objForm.sort.value;
	document.location = strURL;
}
-->
</script>
<?php

html_start_box("<strong>TopX</strong>", "100%", $colors["header"], "3", "center", "");

?>

<tr bgcolor="#<?php print $colors["panel"];?>">
 <td>
  <form name="form_topx" action="topx.php">
   <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
     <td nowrap style='white-space: nowrap;' width="50">
      Age:&nbsp;
     </td>
     <td width="1">
      <select name="age" onChange="applyViewAgeFilterChange(document.form_topx)">

<?php
foreach ($ar_age as $key=>$value)	{
    if ($_SESSION["age"] == $key)
	echo "<option value=\"$key\" selected=\"selected\">$value</option>\n";
    else    
	echo "<option value=\"$key\">$value</option>\n";
}
?>

      </select>
     </td>
     <td nowrap style='white-space: nowrap;' width="50">
      &nbsp;Number of records:&nbsp;
     </td>
     <td width="1">
      <select name="topx" onChange="applyViewAgeFilterChange(document.form_topx)">

<?php
foreach ($ar_topx as $key=>$value)	{
    if ($_SESSION["topx"] == $key)
	echo "<option value=\"$key\" selected=\"selected\">$value</option>\n";
    else
	echo "<option value=\"$key\">$value</option>\n";
}
?>

      </select>
     </td>
     <td nowrap style='white-space: nowrap;' width="20">
      &nbsp;Order:&nbsp;
     </td>
     <td width="1">
      <select name="sort" onChange="applyViewAgeFilterChange(document.form_topx)">
<?php
foreach ($ar_sort as $key=>$value)	{
    if ($_SESSION["sort"] == $key)
	echo "<option value=\"$key\" selected=\"selected\">$value</option>\n";
    else
	echo "<option value=\"$key\">$value</option>\n";
}
?>
      </select>
     </td>
     <td nowrap>
      &nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
      <input type="submit" name="clear_x" value="Clear" title="Clear Filters">
     </td>
    </tr>
  </table>
 </form>
</td>
</tr>

<?php

html_end_box();


// tady zjistit, jake vsechny typy mam (cpu, hdd, ...)
$result = db_fetch_assoc ("select distinct data_template_id, name from plugin_topx_average left join data_template on data_template_id = data_template.id");
if ($result)	{
    echo "<table><tr>\n";
    $cols = 1;

    foreach($result as $row)        {
	if ($cols > 2)	{
	    echo "</tr>\n\n<tr><td>\n";
	    $cols = 1;    
	}
	else	{
	    echo "<td>\n";
	}
	    
	$number_of_ds = db_fetch_cell ("select count(id) from data_template_data where local_data_template_data_id !=0 and data_template_id = " . $row['data_template_id']);

    
	echo "<h3>" . $row['name'] . " (total " . $number_of_ds . "):</h3>\n";

	// zjistime z tabulky, jak to mam sortovat
        $hash  = db_fetch_cell ("SELECT hash from data_template where id=" . $row['data_template_id']  );
        $param = db_fetch_row ("SELECT sorting,operation,unit,final_operation,final_unit from plugin_topx_source where hash='$hash'");
        
	if ($_SESSION["sort"] == "reverse" && $param['sorting'] == "asc")
	    $param['sorting'] = "desc";
	elseif ($_SESSION["sort"] == "reverse" && $param['sorting'] == "desc")
	    $param['sorting'] = "asc";

	$sql = "SELECT * from plugin_topx_average where data_template_id=" . $row['data_template_id'] . " and age='" . $_SESSION["age"] . "' order by result_value " . $param['sorting'];
	if ($_SESSION["topx"] != 0) 
	    $sql .= " limit ". $_SESSION["topx"];

	$result2 = db_fetch_assoc ($sql);
        if ($result2)	{ 
	    // inner
    	    echo "<table  class=\"topx_table\">\n";
    	    
    	    // change name if load_1min=load_1min, ...
    	    
    	    $kde = strpos ($param['operation'], "=");
    	    if ( $kde > 0)	{
    		$param['operation'] = substr($param['operation'],0,$kde);
    	    
    	    }
    	    
    	    echo "<tr><th colspan=\"2\">Host</th><th>" . $param['operation'] . "</th></tr>\n";
	    foreach($result2 as $row2)        {

		$host = db_fetch_row ("select description,hostname from data_local as t1 left join data_template_data as t2 on t1.id=t2.local_data_id left join host on host.id=t1.host_id where t2.local_data_id =" . $row2["local_data_id"]);

		$graph_id = db_fetch_cell ("select distinct(local_graph_id) from graph_templates_item 
					left join data_template_rrd on (graph_templates_item.task_item_id=data_template_rrd.id) 
					left join data_local on (data_template_rrd.local_data_id=data_local.id) 
					left join data_template_data on (data_local.id=data_template_data.local_data_id) 
					where data_template_data.local_data_id=" . $row2['local_data_id']) ;

    		echo "<tr><td><a href=\"" .  htmlspecialchars($config['url_path']) . "graphs.php?action=graph_edit&id=$graph_id\">" . $host["description"] . "</a></td><td>" . $host["hostname"] . "<td>"; // . round($row2["result_value"],2) . " " . $param['unit'];
//echo "<br/>" . $param['final_operation'][0] . "<br/>\n";
//		if ($param['final_unit'])		{


	    $pos = strpos ( $param['final_operation'], "/");
	    if ($pos !== false)		    	{ 
	    	    $cislo = explode("/", $param['final_operation']);
    		    echo round($row2['result_value']/$cislo[1],2) . " " . $param['final_unit'] . "</td>";			    
    	    }
	    elseif ( $param['final_operation'] == "strip")	{
		echo  round($row2["result_value"],2) . "</td>";
	    }		    
	    else	{ // default
		echo  round($row2["result_value"],2) . " " . $param['unit'] . "</td>";
		    
	    }

    	    
    		switch ($row2['age'])       {
            	    case 'quarter':
                	$cycle_required = $poller_interval == 300 ? 3 : 15;
            	    break;
            	    case 'hour':
                	$cycle_required = $poller_interval == 300 ? 12 : 60;
            	    break;
            	    case 'day':
                	$cycle_required = $poller_interval == 300 ? 288 : 1440;
            	    break;
            	    case 'week':
                	$cycle_required = $poller_interval == 300 ? 2016 : 10080;
            	    break;
            	    case 'month':
                	$cycle_required = $poller_interval == 300 ? 8640 : 43200;
            	    break;
        	}   // end of switch
            
        	echo $row2['number_of_cycles'] < $cycle_required ? "<td>(waiting for data)</td>" : " ";  
    		echo "</tr>\n";
	    }
	    
	    echo "</table><br/><br/>\n";

	}
	    echo "</td>\n";
	    $cols++;
    }

    echo "</tr></table>\n\n";
}
else	{	
    echo "No data";
}


bottom_footer();
?>