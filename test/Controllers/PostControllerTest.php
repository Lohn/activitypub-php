<?php
namespace ActivityPub\Test\Controllers;

use ActivityPub\Activities\InboxActivityEvent;
use ActivityPub\Activities\OutboxActivityEvent;
use ActivityPub\Controllers\PostController;
use ActivityPub\Objects\ObjectsService;
use ActivityPub\Test\TestUtils\TestActivityPubObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class PostControllerTest extends TestCase
{
    const OBJECTS = array(
        'https://example.com/actor/1/inbox' => array(
            'id' => 'https://example.com/actor/1/inbox',
        ),
        'https://example.com/actor/1' => array(
            'id' => 'https://example.com/actor/1',
            'inbox' => array(
                'id' => 'https://example.com/actor/1/inbox',
            ),
            'outbox' => array(
                'id' => 'https://example.com/actor/1/outbox',
            ),
        ),
    );
    const REFS = array(
        'https://example.com/actor/1/inbox' => array(
            'field' => 'inbox',
            'referencingObject' => 'https://example.com/actor/1',
        ),
        'https://example.com/actor/1/outbox' => array(
            'field' => 'outbox',
            'referencingObject' => 'https://example.com/actor/1',
        ),
    );

    public function testPostController()
    {
        $objectsService = $this->createMock( ObjectsService::class );
        $objectsService->method( 'query' )->will(
            $this->returnCallback( function( $query ) {
                if ( array_key_exists( 'id', $query ) &&
                     array_key_exists( $query['id'], self::OBJECTS ) ) {
                    $object = TestActivityPubObject::fromArray( self::OBJECTS[$query['id']] );
                    if ( array_key_exists( $query['id'], self::REFS ) ) {
                        $ref = self::REFS[$query['id']];
                        $referencingObject = TestActivityPubObject::fromArray(
                            self::OBJECTS[$ref['referencingObject']]
                        );
                        $referencingField = $referencingObject->getField( $ref['field'] );
                        $object->addReferencingField( $referencingField );
                    }
                    return array( $object );
                } else {
                    return array();
                }
            } )
        );
        $testCases = array(
            array(
                'id' => 'basicInboxTest',
                'request' => $this->makeRequest(
                    'https://example.com/actor/1/inbox',
                    Request::METHOD_POST,
                    '{"type": "Create"}',
                    array(
                        'signed' => true,
                        'actor' => TestActivityPubObject::fromArray( array(
                            'id' => 'https://example.com/actor/1',
                            'inbox' => array(
                                'id' => 'https://example.com/actor/1/inbox',
                            )
                        ) ),
                    )
                ),
                'expectedEventName' => InboxActivityEvent::NAME,
                'expectedEvent' => new InboxActivityEvent(
                    array( 'type' => 'Create' ),
                    TestActivityPubObject::fromArray( self::OBJECTS['https://example.com/actor/1'] ),
                    $this->makeRequest(
                        'https://example.com/actor/1/inbox',
                        Request::METHOD_POST,
                        '{"type": "Create"}',
                        array(
                            'signed' => true,
                            'actor' => TestActivityPubObject::fromArray( array(
                                'id' => 'https://example.com/actor/1',
                                'inbox' => array(
                                    'id' => 'https://example.com/actor/1/inbox',
                                )
                            ) ),
                        )
                    )
                ),
            ),
        );
        foreach ( $testCases as $testCase ) {
            $eventDispatcher = $this->getMockBuilder( EventDispatcher::class )
                             ->setMethods( array( 'dispatch' ) )
                             ->getMock();
            if ( array_key_exists( 'expectedEvent', $testCase ) ) {
                $eventDispatcher->expects( $this->once() )
                    ->method( 'dispatch' )
                    ->with(
                        $this->equalTo($testCase['expectedEventName']),
                        $this->equalTo($testCase['expectedEvent'])
                    );
            }
            $postController = new PostController( $eventDispatcher, $objectsService );
            $request = $testCase['request'];
            if ( array_key_exists( 'expectedException', $testCase ) ) {
                $this->expectException( $testCase['expectedException'] );
            }
            $postController->handle( $request );
        }
    }

    private function makeRequest( $uri, $method, $body, $attributes )
    {
        $request = Request::create(
            $uri, $method, array(), array(), array(), array(), $body
        );
        $request->attributes->add( $attributes );
        // This populates the pathInfo, requestUri, and baseUrl fields on the request:
        $request->getUri();
        return $request;
    }
}
?>