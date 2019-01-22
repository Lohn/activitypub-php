<?php
namespace ActivityPub;

require_once __DIR__ . '/../vendor/autoload.php';

use ActivityPub\Auth\AuthListener;
use ActivityPub\Auth\SignatureListener;
use ActivityPub\Config\ActivityPubModule;
use ActivityPub\Http\ControllerResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;

class ActivityPub
{
    /**
     * @var ActivityPubModule
     */
    private $module;

    /**
     * Constructs a new ActivityPub instance
     *
     * @param array $opts Array of options. Valid keys are
     *     'dbOptions', 'dbprefix', and 'isDevMode'.
     */
    public function __construct( array $opts )
    {
        $this->module = new ActivityPubModule( $opts );
    }

    /**
     * Handles an incoming ActivityPub request
     *
     * @param Request $request (optional) The Symfony request object.
     *   If not passed in, it is generated from the request globals.
     *
     * @return Response The response. Can be sent to the client with $response->send().
     */
    public function handle( $request = null )
    {
        if ( ! $request ) {
            $request = Request::createFromGlobals();
        }

        $dispatcher = $this->module->get( EventDispatcher::class );
        $dispatcher->addSubscriber( $this->module->get( AuthListener::class ) );
        $dispatcher->addSubscriber( $this->module->get( SignatureListener::class ) );
        $dispatcher->addSubscriber( new ExceptionListener() );

        $controllerResolver = $this->module->get( ControllerResolver::class );
        $argumentResolver = new ArgumentResolver();

        $kernel = new HttpKernel(
            $dispatcher, $controllerResolver, new RequestStack(), $argumentResolver
        );
        return $kernel->handle( $request );
    }

    public function updateSchema()
    {
        $entityManager = $this->module->get( EntityManager::class );
        $schemaTool = new SchemaTool( $entityManager );
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->updateSchema( $classes );
    }
}
?>
