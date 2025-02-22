<?php

namespace Ojs\SiteBundle\Controller;

use Ojs\CoreBundle\Controller\OjsController as Controller;
use OpenJournalSoftware\BibtexBundle\Helper\Bibtex;


class ArticleController extends Controller
{
    public function articleWithoutIssuePageAction($slug, $article_id)
    {
        $em = $this->getDoctrine()->getManager();
        $article = $em->getRepository('OjsJournalBundle:Article')->find($article_id);
        $this->throw404IfNotFound($article);
        if($article->getStatus() !== 1 || !$article->getIssue()) {
            $article = null;
            $this->throw404IfNotFound($article);
        }
        $routeParams = array(
            'publisher' => $article->getJournal()->getPublisher()->getSlug(),
            'article_id' => $article->getId(),
            'slug' => $article->getJournal()->getSlug(),
            'issue_id' => $article->getIssue()->getId()
        );
        return $this->redirectToRoute('ojs_article_page', $routeParams);
    }

    /**
     * @param $slug
     * @param $article_id
     * @param null $issue_id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function articlePageAction($slug, $article_id, $issue_id = null)
    {

        $journalService = $this->get('ojs.journal_service');
        $em = $this->getDoctrine()->getManager();
        $data['article'] = $em->getRepository('OjsJournalBundle:Article')->find($article_id);
        $this->throw404IfNotFound($data['article']);
        //log article view event

        $bibtex = new Bibtex();
        $bibtex->_options['extractAuthors'] = true;
        $bibtex->_options['wordWrapWidth'] = 0;
        $bibtex->authorstring = 'VON LAST, JR, FIRST';

        $createGetterFunction = 'get' . ucfirst('title');

        $fieldTranslations = [];
        foreach ($data['article']->getTranslations() as $langCode => $translation) {
            if (!empty($translation->$createGetterFunction()) && $translation->$createGetterFunction() != '-') {
                $fieldTranslations[$langCode] = $translation->$createGetterFunction();

                /*
                 @article{seligman01impact,
                 author = {Len Seligman and Arnon Rosenthal},
                 title = {{XML}'s Impact on Databases and Data Sharing},
                 journal = {Computer},
                 volume = {34},
                 number = {6},
                 year = {2001},
                 issn = {0018-9162},
                 pages = {59--67},
                 doi = {http://dx.doi.org/10.1109/2.928623},
                 publisher = {IEEE Computer Society Press},
                 address = {Los Alamitos, CA, USA},
                 }
                 */
                $addarray = array();
                $addarray['entryType'] = $data['article']->getArticleType();
                $addarray['journal'] = $data['article']->getJournal()->getTitle();
                $addarray['issn'] = $data['article']->getJournal()->getIssn();
                $addarray['address'] = $data['article']->getJournal()->getAddress();
                $addarray['address'] = $data['article']->getJournal()->getPublisher()->getName();
                $addarray['year'] = $data['article']->getJournal()->getFounded()->format('Y');
                $addarray['volume'] = $data['article']->getIssue()->getVolume();
                $addarray['pages'] = $data['article']->getFirstPage() . ' - ' . $data['article']->getLastPage();
                $addarray['doi'] = $data['article']->getDoi();
                $addarray['title'] = $translation->$createGetterFunction();
                $addarray['language'] = $langCode;
                $addarray['cite'] = $data['article']->getJournal()->getSlug() . $data['article']->getId();
                $addarray['key'] = 'cite';
                foreach ($data['article']->getArticleAuthors() as $author) {
                    $addarray['author'][$author->getAuthorOrder()]['first'] = $author->getAuthor()->getFirstName();
                    $addarray['author'][$author->getAuthorOrder()]['last'] = $author->getAuthor()->getLastName();
                    //$addarray['author'][]['jr'] = $author->getAuthor()->getMiddleName();

                }
                arsort($addarray['author']);
                $bibtex->addEntry($addarray);

                unset($addarray);
            }
        }
        $data['bibtex'] = ltrim(rtrim(print_r($bibtex->bibTex(), 1)));


        $data['schemaMetaTag'] = '<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />';
        $data['meta'] = $this->get('ojs.article_service')->generateMetaTags($data['article']);
        $data['journal'] = $data['article']->getJournal();
        $data['page'] = 'journals';
        $data['blocks'] = $em->getRepository('OjsJournalBundle:Block')->journalBlocks($data['journal']);
        $data['journal']->setPublicURI($journalService->generateUrl($data['journal']));
        $data['archive_uri'] = $this->generateUrl('ojs_archive_index', [
            'slug' => $data['journal']->getSlug(),
            'publisher' => $data['journal']->getPublisher()->getSlug(),
        ], true);
        $data['token'] = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('article_view');


        return $this->render('OjsSiteBundle:Article:article_page.html.twig', $data);
    }
}
