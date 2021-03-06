<?php

namespace TechPromux\BaseBundle\Manager\Resource;

use Doctrine\Common\Collections\ArrayCollection;
use TechPromux\BaseBundle\Entity\Resource\BaseResourceTree;

/**
 * Class BaseResourceTreeManager
 * @package TechPromux\BaseBundle\Manager\Resource
 */
abstract class BaseResourceTreeManager extends BaseResourceManager
{

    /**
     * Modify Base Query with custom options
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $options
     * @param string $action
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function alterBaseQueryBuilder($query, $options = array(), $action = 'list')
    {
        $query = parent::alterBaseQueryBuilder($query, $options, $action);

        $parent_tree = $this->findBaseRootElement();

        if (!is_null($parent_tree)) {
            $query->andWhere(
                $query->getRootAliases()[0] . '.lft <= ' . $this->addNamedParameter('rgt', $parent_tree->getRgt(), $query)
                . ' AND ' . $query->getRootAliases()[0] . '.lft >= ' . $this->addNamedParameter('lft', $parent_tree->getLft(), $query)
            );
        }

        $query->andWhere($query->getRootAliases()[0] . '.parent IS NOT NULL');

        $query->addOrderBy($query->getRootAliases()[0] . '.lft', 'ASC');

        return $query;
    }

    /**
     * Modify Base Query with custom options for elements from parent node
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param integer $parent_id
     * @param boolean $include_parent
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function alterQueryBuilderWithParentRoot($query, $parent_id, $include_parent = true)
    {
        if (is_null($parent_id)) {
            return $query;
        }
        $parent = $this->find($parent_id);

        $query->andWhere(
            $query->getRootAliases()[0] . '.lft >=' . $this->addNamedParameter('lft', $parent->getLft(), $query)
            . ' AND ' .
            $query->getRootAliases()[0] . '.lft <=' . $this->addNamedParameter('rgt', $parent->getRgt(), $query)
        );
        if (!$include_parent) {
            $query->andWhere(
                $query->getRootAliases()[0] . '.id !=' . $this->addNamedParameter('id', $parent_id, $query)
            );
        }
        return $query;
    }

    /**
     * Modify Base Query with custom options for elements except from parent node
     *
     * @param \Doctrine\ORM\QueryBuilder $query
     * @param array $options
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function alterQueryBuilderExceptWithParentRoot($query, $parent_id)
    {
        if (is_null($parent_id)) {
            return $query;
        }
        $parent = $this->find($parent_id);
        $query->andWhere(
            $query->getRootAliases()[0] . '.lft > ' . $this->addNamedParameter('rgt', $parent->getRgt(), $query)
            . ' OR ' . $query->getRootAliases()[0] . '.lft < ' . $this->addNamedParameter('lft', $parent->getLft(), $query)
        );
        return $query;
    }


    /**
     * Create Base Query with custom options for elements from parent node
     *
     * @param integer $parent_id
     * @param boolean $include_parent
     *
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function createQueryBuilderWithParentRoot($parent_id, $include_parent = true)
    {
        $query = $this->createQueryBuilder();
        return $this->alterQueryBuilderWithParentRoot($query, $parent_id, $include_parent);
    }

    /**
     * Create Base Query with custom options for elements except from parent node
     *
     * @param integer $parent_id
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function createQueryBuilderExceptWithParentRoot($parent_id)
    {
        $query = $this->createQueryBuilder();
        return $this->alterQueryBuilderExceptWithParentRoot($query, $parent_id);
    }

    // -----------------------------------------------------------------------

    /**
     * @return BaseResourceTree
     */
    public function createNewInstance()
    {
        return parent::createNewInstance(); // TODO: Change the autogenerated stub
    }

    /**
     * Create a base root element
     *
     * @return BaseResourceTree
     */
    protected function createBaseRootElement()
    {
        $root = $this->createNewInstance();

        $root->setIsRoot(true);

        $root->setName('_ROOT_' . strtoupper($this->getResourceName()));
        $root->setTitle('_ROOT_' . strtoupper($this->getResourceName()));
        $root->setDescription('ROOT node for ' . $this->getResourceName());
        $root->setLevel(-1);
        $root->setPosition(0);
        $root->setLft(0);
        $root->setRgt(0);
        $root->setRgt(PHP_INT_MAX);

        $root->setParent(null);
        $root->setEnabled(true);

        $this->persist($root);

        $this->updateLftRgtForAllElements();

        return $root;
    }

    /**
     * Find an unique root element or null
     *
     * @return BaseResourceTree
     */
    protected function findBaseRootElementOrNull()
    {
        $qb = parent::createBaseQueryBuilder();

        $qb->andWhere($qb->getRootAliases()[0] . '.isRoot = 1');
        $qb->andWhere($qb->getRootAliases()[0] . '.parent IS NULL');

        $root = $qb->getQuery()->getOneOrNullResult();

        return $root;
    }

    /**
     * Find an unique root element or create one if it doesn´t exists
     *
     * @return BaseResourceTree
     */
    protected function findBaseRootElement()
    {
        $root = $this->findBaseRootElementOrNull();
        if (is_null($root)) {
            $root = $this->createBaseRootElement();
        }
        return $root;
    }

    /**
     * Get all elements of a parent node
     *
     * @param string $parent_id
     * @return ArrayCollection
     */
    public function findChildrenByParentId($parent_id)
    {
        $qb = $this->createQueryBuilder();
        $qb->andWhere($qb->getRootAliases()[0] . '.parent = ' . $this->addNamedParameter('parent', $parent_id, $qb))
            ->orderBy($qb->getRootAliases()[0] . '.position', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * Get all elements of a parent node
     *
     * @param string $name
     * @return ArrayCollection
     */
    public function findChildrenByParentName($name)
    {
        $parent = $this->findOneByName($name);

        return $this->findChildrenByParentId($parent->getId());
    }

    /**
     * Get all elements of a parent node
     *
     * @param string $parent_id
     * @return ArrayCollection
     */
    public function findChildrenTreeByParentId($parent_id)
    {
        $qb = $this->createQueryBuilder();

        $qb = $this->alterQueryBuilderWithParentRoot($qb, $parent_id);

        $qb->orderBy($qb->getRootAliases()[0] . '.lft', 'ASC');

        return $this->getResultFromQueryBuilder($qb);
    }

    /**
     * Get all elements except of a parent node
     *
     * @param string $parent_id
     * @return ArrayCollection
     */
    public function findAllExceptTreeByParentId($parent_id)
    {
        $qb = $this->createQueryBuilder();

        $qb = $this->alterQueryBuilderExceptWithParentRoot($qb, $parent_id);

        $qb->orderBy($qb->getRootAliases()[0] . '.lft', 'ASC');

        return $this->getResultFromQueryBuilder($qb);
    }

    /**
     * Get all elements except of a parent node
     *
     * @param string $name
     * @return ArrayCollection
     */
    public function findAllExceptTreeByParentName($name)
    {
        $parent = $this->findOneByName($name);

        return $this->findAllExceptTreeByParentId($parent->getId());
    }

    /**
     * Get all children elements of root element
     *
     * @return ArrayCollection
     */
    public function findRootChildrenElements()
    {
        $root = $this->findBaseRootElement();
        return $this->findChildrenByParentId($root->getId());
    }

// --------------------------------------------------------------------------

    /**
     * Actualiza las posiciones de los nodos de los subordinados de su superior (su hermanos)
     *
     * @param \TechPromux\BaseBundle\Entity\BaseResourceTree $object
     * @param boolean $remove
     */
    public function updatePositionForSiblingsElements($object, $removed = false)
    {

        if (!is_null($object->getParent())) {
            $children = $this->findChildrenByParentId($object->getParent()->getId());
            $i = 1;
            $f = false;
            foreach ($children as $ch) {
                if ($removed && $ch->getId() != $object->getId()) {
                    $ch->setPosition($i);
                    $this->updateWithoutPreAndPostUpdate($ch);
                    $i++;
                } else if (!$removed) {
                    if (!$f && $ch->getPosition() >= $object->getPosition()) {
                        $f = true;
                        $object->setPosition($i);
                        if ($ch->getId() != $object->getId()) {
                            $ch->setPosition($i + 1);
                            $i++;
                        }
                    } else if ($ch->getId() != $object->getId()) {
                        $ch->setPosition($i);
                    }
                    $this->updateWithoutPreAndPostUpdate($ch);
                    $i++;
                }
            }
        }

        return true;
    }

    /**
     * Actualiza los niveles de todos los elementos
     *
     * @param \TechPromux\BaseBundle\Entity\BaseResourceTree $object
     */
    public function updateLevelForChildrenElements($object)
    {

        $tmp = new \Doctrine\Common\Collections\ArrayCollection();

        $children = $this->findChildrenByParentId($object->getId());

        foreach ($children as $ch) {
            $tmp->add($ch);
        }

        while (!$tmp->isEmpty()) {
            $d = $tmp->first();
            $tmp->removeElement($d);
            $children = $d->getChildren();
            foreach ($children as $ch) {
                $tmp->add($ch);
            }
            $parent = $d->getParent();
            $d->setLevel($parent->getLevel() + 1);
            $this->updateWithoutPreAndPostUpdate($d);
        }

    }

    /**
     * Actualiza las posiciones lft y rgt de los nodos
     *
     */
    public function updateLftRgtForAllElements()
    {
        $root = $this->findBaseRootElement();

        if (!is_null($root)) {
            $this->updateLftRgtForAllElements_Deep_Recursive_Course($root, 0);
        }
    }

    /**
     * Actualiza de forma recursiva las posiciones lft y rgt de los nodos
     *
     * @param \TechPromux\BaseBundle\Entity\BaseResourceTree $object
     * @param integer $cont
     * @return integer
     */
    protected function updateLftRgtForAllElements_Deep_Recursive_Course($object, $cont)
    {
        $cont++;
        $object->setLft($cont);
        $children = $this->findChildrenByParentId($object->getId());

        foreach ($children as $ch) {
            $cont = $this->updateLftRgtForAllElements_Deep_Recursive_Course($ch, $cont);
        }
        $object->setRgt($cont);
        $this->updateWithoutPreAndPostUpdate($object);
        return $cont;
    }

// ---------------------------------------------------------------------------------

    /**
     * @param BaseResourceTree $object
     */
    public function prePersist($object)
    {
        parent::prePersist($object);

        if (is_null($object->getIsRoot()) || !$object->getIsRoot()) {

            $object->setIsRoot(false);

            if (is_null($object->getParent())) {
                $parent = $this->findBaseRootElement();
                /* @var $root \TechPromux\BaseBundle\Entity\BaseResourceTree */
                $object->setParent($parent);
            } else {
                $parent = $object->getParent();
            }
            $object->setLevel($parent->getLevel() + 1);
            $object->setLft($parent->getLft());
            $object->setRgt($parent->getRgt());
        }

        if (is_null($object->getPosition())) {
            $object->setPosition(PHP_INT_MAX);
        }
    }

    /**
     * @param BaseResourceTree $object
     */
    public function postPersist($object)
    {
        parent::postPersist($object);

        $this->updatePositionForSiblingsElements($object);
    }

    /**
     * @param BaseResourceTree $object
     */
    public function preUpdate($object)
    {
        parent::preUpdate($object);

        if (is_null($object->getParent())) {
            $root = $this->findBaseRootElementOrNull();
            /* @var $root BaseResourceTree */
            $object->setParent($root);
            $object->setLevel(is_null($root) ? -1 : 0);
            $object->setLft(is_null($root) ? 0 : $root->getRgt());
            $object->setRgt(is_null($root) ? 0 : $root->getRgt());
        } else {
            $parent = $object->getParent();
            /* @var $parent BaseResourceTree */
            $object->setLevel($parent->getLevel() + 1);
            $object->setLft($parent->getRgt());
            $object->setRgt($parent->getRgt());
        }
        if (is_null($object->getPosition())) {
            $object->setPosition(PHP_INT_MAX);
        }
    }

    /**
     * @param BaseResourceTree $object
     */
    public function postUpdate($object)
    {
        parent::postUpdate($object);

        $this->updatePositionForSiblingsElements($object);

        $this->updateLevelForChildrenElements($object);
    }

    /**
     * @param BaseResourceTree $object
     */
    public function preRemove($object)
    {
        parent::preRemove($object);
        $this->removeElementAndChildren($object);
        $this->updatePositionForSiblingsElements($object, true);
    }

    /**
     * @param BaseResourceTree $object
     */
    public function removeElementAndChildren($object)
    {

        $delete_stack = array();
        $tmp = new \Doctrine\Common\Collections\ArrayCollection();
        $children = $object->getChildren();
        foreach ($children as $ch) {
            $tmp->add($ch);
        }

        while (!$tmp->isEmpty()) {
            $t = $tmp->first();
            $tmp->removeElement($t);
            $children = $t->getChildren();
            foreach ($children as $ch) {
                $tmp->add($ch);
            }
            $delete_stack[] = $t;
        }
        for ($i = count($delete_stack) - 1; $i >= 0; $i--) {
            $t = $delete_stack[$i];
            $this->removeWithoutPreAndPostRemove($t);
        }
    }

    /**
     * @param BaseResourceTree $object
     */
    public function postRemove($object)
    {
        parent::postRemove($object);

    }

}
