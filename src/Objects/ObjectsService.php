<?php
namespace ActivityPub\Objects;

use ActivityPub\Entities\ActivityPubObject;
use ActivityPub\Entities\Field;
use ActivityPub\Utils\Util;
use Doctrine\ORM\EntityManager;

class ObjectsService
{
    protected $entityManager;

    public function __construct( EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Creates a new object with fields defined by $fields
     *
     // TODO make this happen:
     * If there is an 'id' field, and an object with that id already exists,
     *   this returns the existing object rather than creating the new object.
     *   The existing object will not have its fields modified.
     *
     * @param array $fields The fields that define the new object
     * @return ActivityPubObject The created object
     */
    public function createObject( array $fields )
    {
        // TODO validate object fields?
        // TODO don't create the object if it already exists
        // i.e. (an 'id' field exists with the same value as this one)
        // TODO attempt to fetch and create any values that are URLs
        $object = new ActivityPubObject();
        $this->entityManager->persist( $object );
        foreach ( $fields as $name => $value ) {
            $this->persistField( $object, $name, $value );
        }
        $this->entityManager->flush();
        return $object;
    }

    /**
     * Persists a field. 
     *
     * If the field is a sequential array, persists all the fields in the array.
     *   If the field is an associative array, create that as a new object 
     *   (or fetch the existing object) and persist that as the field's target object.
     *
     * @param ActivityPubObject $object
     * @param string $fieldName
     * @param string|array $fieldValue
     *
     */
    private function persistField( $object, $fieldName, $fieldValue )
    {
        if ( is_string( $fieldValue ) ) {
            $fieldEntity = Field::withValue( $object, $fieldName, $fieldValue );
            $this->entityManager->persist( $fieldEntity);
        } else if ( is_array( $fieldValue ) ) {
            if ( Util::isAssoc( $fieldValue ) ) {
                $referencedObject = $this->createObject( $fieldValue );
                $fieldEntity = Field::withObject( $object, $fieldName, $referencedObject );
                $this->entityManager->persist( $fieldEntity );
            } else {
                foreach( $fieldValue as $subValue ) {
                    $this->persistField( $object, $fieldName, $subValue );
                }
            }
        }
    }

    /**
     * Queries for an object with certain field values
     *
     * @param array $queryTerms An associative array where the keys are field 
     *   names and the values are the values to query for. The value for a key
     *   can also be another associative array, which represents a field
     *   containing a target object that matches the given nested query.
     *   Finally, the value could be a sequential array, which represents a field
     *   containing all of the specified values (the field could also contain more
     *   values).
     *
     * @return ActivityPubObject[] The objects that match the query, if any,
     *   ordered by created timestamp from newest to oldest
     */
    public function query( $queryTerms )
    {
        $qb = $this->entityManager->createQueryBuilder();
        $depth = 0;
        $qb->select( "object$depth" )
            ->from( '\ActivityPub\Entities\ActivityPubObject', "object$depth" )
            ->join( "object$depth.fields", "field$depth" )
            ->where( $this->getWhereExpr( $qb, $queryTerms, $depth ) );
        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * Generates the expression that gets passed into the query WHERE clause
     *
     * This function is recursive; it traverses the query tree to build up the 
     *   final expression
     *
     * @param QueryBuilder $qb The query builder that the WHERE clause will be attached to
     * @param array $queryTerms The query terms from which to generate the expressions
     * @param int $depth The recursion depth
     * @return Expr The expression
     */
    protected function getWhereExpr( &$qb, $queryTerms, $depth = 0 )
    {
        $nextDepth = $depth + 1;
        $exprs = array();
        foreach( $queryTerms as $fieldName => $fieldValue ) {
            if ( is_array( $fieldValue ) ) {
                if ( Util::isAssoc( $fieldValue ) ) {
                    $subQuery = $this->entityManager->createQueryBuilder();
                    $subQuery->select( "object$nextDepth" )
                        ->from( '\ActivityPub\Entities\ActivityPubObject', "object$nextDepth" )
                        ->join( "object$nextDepth.fields", "field$nextDepth" )
                        ->where( $this->getWhereExpr( $subQuery, $fieldValue, $nextDepth ) );
                    $exprs[] = $qb->expr()->andX(
                        $qb->expr()->like( "field$depth.name", $qb->expr()->literal( $fieldName ) ),
                        $qb->expr()->in( "field$depth.targetObject", $subQuery->getDql())
                    );
                } else {
                    $subExprs = array();
                    foreach ( $fieldValue as $subQuery ) {
                        $subExprs[] = $this->getWhereExpr(
                            $qb, array( $fieldName => $subQuery ), $depth
                        );
                    }
                    $exprs[] = call_user_func_array(
                        array( $qb->expr(), 'orX' ),
                        $subExprs
                    );
                }
            } else {
                $exprs[] = $qb->expr()->andX(
                    $qb->expr()->like( "field$depth.name", $qb->expr()->literal( $fieldName ) ),
                    $qb->expr()->like( "field$depth.value", $qb->expr()->literal( $fieldValue ) )
                );
            }
        }
        return call_user_func_array(
            array( $qb->expr(), 'andX' ),
            $exprs
        );
    }
}
?>
