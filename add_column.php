<?php
// Cleanup temp file - this was only used to add the can_reserve column
// The column definition is now in db.php schema
unlink(__FILE__);
echo "Cleanup complete";