<?php

if( !empty($_GET['hash']))
{
    $_GET['paylane_hash'] = $_GET['hash'];
    unset($_GET['hash']);
}

if( !empty($_POST['hash']))
{
    $_POST['paylane_hash'] = $_POST['hash'];
    unset($_POST['hash']);
}

require('includes/application_top.php');

if( isset($_GET['correct']))
{
    if ( !empty($_GET['main_page']))
    {
        unset($_GET['main_page']);
    }
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PROCESS, http_build_query($_GET), 'SSL', true, false));
}
elseif( isset($_POST['correct']))
{
 ?>
<html>
<head>
    <title>Paylane</title>
</head>
<body>
<form action="<?php echo zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'); ?>" method="post" id="paylane_form" class="hidden">
    <?php

    foreach ($_POST as $k => $v)
    {
        echo '<input type="hidden" name="' . $k . '" value="' . $v . '" />';
    }

    ?>
</form>
<script type="text/javascript">
    document.forms["paylane_form"].submit();
</script>
</body>
</html>
<?php
}
else
{
    zen_redirect(zen_href_link(FILENAME_DEFAULT));
}

require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
