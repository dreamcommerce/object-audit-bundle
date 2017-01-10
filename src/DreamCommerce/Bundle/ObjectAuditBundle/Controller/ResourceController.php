<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace DreamCommerce\Bundle\ObjectAuditBundle\Controller;

use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Resource\Metadata\MetadataInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for listing auditing information.
 *
 * @author Tim Nagel <tim@nagel.com.au>
 */
final class AuditController
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EngineInterface
     */
    private $templatingEngine;

    /**
     * @var ResourceAuditManagerInterface
     */
    private $auditManager;

    /**
     * @var RegistryInterface
     */
    private $resourceRegistry;

    /**
     * @param ContainerInterface                     $container
     * @param EngineInterface               $templatingEngine
     * @param ResourceAuditManagerInterface $auditManager
     * @param RegistryInterface             $resourceRegistry
     */
    public function __construct(
        ContainerInterface $container,
        EngineInterface $templatingEngine,
        ResourceAuditManagerInterface $auditManager,
        RegistryInterface $resourceRegistry
    ) {
        $this->templatingEngine = $templatingEngine;
        $this->auditManager = $auditManager;
        $this->resourceRegistry = $resourceRegistry;
    }

    /**
     * Renders a paginated list of revisions.
     *
     * @param int $page
     *
     * @return Response
     */
    public function indexAction($page = 1)
    {
        /** @var Pagerfanta $paginator */
        $paginator = $this->auditManager->getObjectAuditManager()->getRevisionRepository()->createPaginator();
        $paginator->setCurrentPage($page);

        return $this->templatingEngine->renderResponse('DreamCommerceObjectAuditBundle:Audit:index.html.twig', [
            'revisions' => $paginator,
        ]);
    }

    /**
     * Shows resources changed in the specified revision.
     *
     * @param int $revisionId
     *
     * @return Response
     */
    public function viewRevisionAction($revisionId)
    {
        $revision = $this->getRevision($revisionId);

        return $this->templatingEngine->renderResponse('DreamCommerceObjectAuditBundle:Audit:view_revision.html.twig', [
            'revision' => $revision,
            'changedResources' => $this->auditManager->findAllResourcesChangedAtRevision($revision),
        ]);
    }

    /**
     * Lists revisions for the supplied resource.
     *
     * @param string $resourceName
     * @param int    $resourceId
     *
     * @return Response
     */
    public function viewResourceAction($resourceName, $resourceId)
    {
        $resource = $this->getResource($resourceName, $resourceId);

        return $this->templatingEngine->renderResponse('DreamCommerceObjectAuditBundle:Audit:view_resource.html.twig', array(
            'resourceId' => $resourceId,
            'resourceName' => $resourceName,
            'resource' => $resource,
            'revisions' => $this->auditManager->findResourceRevisions($resourceName, $resourceId),
        ));
    }

    /**
     * Shows the data for an resource at the specified revision.
     *
     * @param string $resourceName
     * @param int    $resourceId
     * @param int    $revisionId
     *
     * @return Response
     */
    public function viewDetailAction($resourceName, $resourceId, $revisionId)
    {
        $this->getResourceMetadata($resourceName);
        $revision = $this->getRevision($revisionId);
        $resource = $this->auditManager->findResourceByRevision($resourceName, $resourceId, $revision);

        $data = $this->auditManager->getResourceValues($resource);
        krsort($data);

        return $this->templatingEngine->renderResponse('DreamCommerceObjectAuditBundle:Audit:view_detail.html.twig', [
            'resourceId' => $resourceId,
            'revision' => $revision,
            'resourceName' => $resourceName,
            'resource' => $resource,
            'data' => $data,
        ]);
    }

    /**
     * Compares an resource at 2 different revisions.
     *
     * @param Request  $request
     * @param string   $resourceName
     * @param int      $resourceId
     * @param null|int $oldRevisionId if null, pulled from the query string
     * @param null|int $newRevisionId if null, pulled from the query string
     *
     * @return Response
     */
    public function compareAction(Request $request, $resourceName, $resourceId, $oldRevisionId = null, $newRevisionId = null)
    {
        $revisionRepository = $this->auditManager->getObjectAuditManager()->getRevisionRepository();

        if ($oldRevisionId === null) {
            $oldRevisionId = $request->query->get('oldRev');
        }
        if ($newRevisionId === null) {
            $newRevisionId = $request->query->get('newRev');
        }

        $this->getResourceMetadata($resourceName);

        if (empty($oldRevisionId)) {
            $oldRevision = $this->auditManager->getInitializeResourceRevision($resourceName, $resourceId);
            if ($oldRevision === null) {
                throw new NotFoundHttpException('The resource identified by name #'.$resourceName.' and ID #'.$resourceId.' does not exist');
            }
        } else {
            /** @var RevisionInterface $oldRevision */
            $oldRevision = $revisionRepository->find($oldRevisionId);
            if ($oldRevision === null) {
                throw new NotFoundHttpException('The revision identified by ID #'.$oldRevisionId.' does not exist');
            }
        }

        if (empty($newRevisionId)) {
            $newRevision = $this->auditManager->getCurrentResourceRevision($resourceName, $resourceId);
        } else {
            /** @var RevisionInterface $newRevision */
            $newRevision = $revisionRepository->find($newRevisionId);
            if ($newRevision === null) {
                throw new NotFoundHttpException('The revision identified by ID #'.$newRevisionId.' does not exist');
            }
        }

        if ($oldRevision->getId() == $newRevision->getId()) {
            throw new NotFoundHttpException('Nothing to compare, same revisions');
        }

        if ($oldRevision->getId() > $newRevision->getId()) {
            $tmpRevision = $oldRevision;
            $oldRevision = $newRevision;
            $newRevision = $tmpRevision;
        }

        $diff = $this->auditManager->diffResourceRevisions($resourceName, $resourceId, $oldRevision, $newRevision);

        return $this->templatingEngine->renderResponse('DreamCommerceObjectAuditBundle:Audit:compare.html.twig', [
            'resourceName' => $resourceName,
            'resourceId' => $resourceId,
            'oldRevision' => $oldRevision,
            'newRevision' => $newRevision,
            'diff' => $diff,
        ]);
    }

    /**
     * @param string $resourceName
     * @param int    $resourceId
     *
     * @return ResourceInterface
     *
     * @throws NotFoundHttpException
     */
    private function getResource($resourceName, $resourceId)
    {
        $metadata = $this->getResourceMetadata($resourceName);
        /** @var RepositoryInterface $resourceRepository */
        $resourceRepository = $this->container->get($metadata->getServiceId('repository'));
        /** @var ResourceInterface $resource */
        $resource = $resourceRepository->find($resourceId);
        if ($resource === null) {
            throw new NotFoundHttpException('Resource identified by name '.$resourceName.' and ID #'.$resourceId.' does not exist');
        }

        return $resource;
    }

    /**
     * @param string $resourceName
     *
     * @return MetadataInterface
     */
    private function getResourceMetadata($resourceName)
    {
        try {
            $metadata = $this->resourceRegistry->get($resourceName);
        } catch (\InvalidArgumentException $exc) {
            throw new NotFoundHttpException($exc->getMessage(), 0, $exc);
        }

        return $metadata;
    }

    /**
     * @param mixed $revisionId
     *
     * @return RevisionInterface
     *
     * @throws NotFoundHttpException
     */
    private function getRevision($revisionId)
    {
        $revisionRepository = $this->auditManager->getObjectAuditManager()->getRevisionRepository();
        /** @var RevisionInterface $revision */
        $revision = $revisionRepository->find($revisionId);
        if ($revision === null) {
            throw new NotFoundHttpException('Revision identified by ID #'.$revisionId.' does not exist');
        }

        return $revision;
    }
}