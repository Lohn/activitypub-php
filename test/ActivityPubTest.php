<?php
require_once dirname( __FILE__ ) . '/config/SQLiteTestCase.php';
require_once dirname( __FILE__ ) . '/config/ArrayDataSet.php';
    
use ActivityPub\ActivityPub;
use ActivityPub\Config\SQLiteTestCase;
use ActivityPub\Config\ArrayDataSet;

class ActivityPubTest extends SQLiteTestCase
{
    public function getDataSet() {
        return new ArrayDataSet( array() );
    }
    
    public function testItCreatesSchema() {
        $this->assertTrue( file_exists( $this->getDbPath() ) );
    }

    /**
     * @depends testItCreatesSchema
     */
    public function testItUpdatesSchema() {
        $activityPub = new ActivityPub(array(
            'dbOptions' => array(
                'driver' => 'pdo_sqlite',
                'path' => $this->getDbPath(),
            ),
        ) );
        $activityPub->updateSchema();
        $this->assertTrue( file_exists( $this->getDbPath() ) );
    }

    private function getDbPath() {
        return dirname( __FILE__ ) . '/db.sqlite';
    }
}
?>
