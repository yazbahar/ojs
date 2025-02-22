<?php

namespace Ojs\ApiBundle\Handler;

use Doctrine\Common\Persistence\ObjectManager;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\ArticleFile;
use Ojs\JournalBundle\Form\Type\ArticleFileType;
use Ojs\JournalBundle\Service\JournalService;
use Symfony\Component\Form\FormFactoryInterface;
use Ojs\ApiBundle\Exception\InvalidFormException;
use Symfony\Component\Filesystem\Filesystem;
use Ojs\CoreBundle\Service\ApiHandlerHelper;
use Symfony\Component\HttpKernel\KernelInterface;

class JournalArticleFileHandler
{
    private $om;
    private $entityClass;
    private $repository;
    private $formFactory;
    private $journalService;
    private $kernel;
    private $apiHelper;

    public function __construct(ObjectManager $om, $entityClass, FormFactoryInterface $formFactory, JournalService $journalService, KernelInterface $kernel, ApiHandlerHelper $apiHelper)
    {
        $this->om = $om;
        $this->entityClass = $entityClass;
        $this->repository = $this->om->getRepository($this->entityClass);
        $this->formFactory = $formFactory;
        $this->journalService = $journalService;
        $this->kernel = $kernel;
        $this->apiHelper = $apiHelper;
    }

    /**
     * Get a ArticleFile.
     *
     * @param mixed $id
     *
     * @return ArticleFile
     */
    public function get($id)
    {
        /** @var ArticleFile $entity */
        $entity = $this->repository->find($id);
        return $this->apiHelper->normalizeEntity($entity);
    }

    /**
     * Get a list of ArticleFiles.
     *
     * @param int $limit  the limit of the result
     * @param int $offset starting from the offset
     *
     * @return array
     */
    public function all($limit = 5, $offset = 0)
    {
        return $this->repository->findBy([
            'article' => $this->getArticle()
        ], null, $limit, $offset);
    }

    /**
     * Create a new ArticleFile.
     *
     * @param array $parameters
     *
     * @return ArticleFile
     */
    public function post(array $parameters)
    {
        $entity = $this->createArticleFile();
        return $this->processForm($entity, $parameters, 'POST');
    }

    /**
     * Edit a ArticleFile.
     *
     * @param ArticleFile $entity
     * @param array         $parameters
     *
     * @return ArticleFile
     */
    public function put(ArticleFile $entity, array $parameters)
    {
        return $this->processForm($entity, $parameters, 'PUT');
    }

    /**
     * Partially update a ArticleFile.
     *
     * @param ArticleFile $entity
     * @param array         $parameters
     *
     * @return ArticleFile
     */
    public function patch(ArticleFile $entity, array $parameters)
    {
        return $this->processForm($entity, $parameters, 'PATCH');
    }

    /**
     * Delete a ArticleFile.
     *
     * @param ArticleFile $entity
     *
     * @return ArticleFile
     */
    public function delete(ArticleFile $entity)
    {
        $this->om->remove($entity);
        $this->om->flush();
        return $this;
    }

    /**
     * Get Article.
     *
     * @return Article
     */
    public function getArticle()
    {
        $request = $this->apiHelper->getRequestStack()->getCurrentRequest();
        if(!$request) {
            return false;
        }
        $articleId = $request->attributes->get('articleId');
        if (!$articleId) {
            return false;
        }
        /** @var Article $selectedArticle */
        $selectedArticle = $this->om->getRepository('OjsJournalBundle:Article')->findOneBy([
            'id' => $articleId,
            'journal' => $this->journalService->getSelectedJournal()
        ]);
        if (!$selectedArticle) {
            return false;
        }
        return $selectedArticle;
    }

    /**
     * Processes the form.
     *
     * @param ArticleFile $entity
     * @param array         $parameters
     * @param String        $method
     *
     * @return ArticleFile
     *
     * @throws \Ojs\ApiBundle\Exception\InvalidFormException
     */
    private function processForm(ArticleFile $entity, array $parameters, $method = "PUT")
    {
        $form = $this->formFactory->create(new ArticleFileType(), $entity, array(
            'method' => $method,
            'csrf_protection' => false,
            'locales' => $this->journalService->getJournalLocales(),
        ));
        $form->submit($parameters, 'PATCH' !== $method);
        $formData = $form->getData();

        $file = $formData->getFile();
        if(isset($file)){
            $entity->setFile($this->storeFile($file, false));
        }
        if ($form->isValid()) {
            $entity->setArticle($this->getArticle());
            $this->om->persist($entity);
            $this->om->flush();
            return $formData;
        }
        throw new InvalidFormException('Invalid submitted data', $form);
    }

    private function storeFile($file)
    {
        $rootDir = $this->kernel->getRootDir();
        $articleFileDir = $rootDir . '/../web/uploads/articlefiles/';

        $fs = new Filesystem();
        $fs->mkdir($articleFileDir);
        $fs->dumpFile($articleFileDir . $file['filename'], base64_decode($file['encoded_content']));
        return $file['filename'];
    }

    private function createArticleFile()
    {
        return new $this->entityClass();
    }
}