<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Forms\ShowType;
use AppBundle\Forms\EpisodeType;
use AppBundle\Entity\TVShow;
use AppBundle\Entity\Season;
use AppBundle\Entity\Episode;
use aharen\OMDbAPI;

/**
 * @Route("/admin")
 */
class AdminController extends Controller
{
    /**
     * @Route("/addShow", name="admin_add_show")
     * @Template()
     */
    public function addShowAction(Request $request)
    {
    	$show = new TVShow;
    	$form = $this->createForm(ShowType::class, $show);
    	$success = false;

		$form->handleRequest($request);
    	if ($form->isSubmitted() && $form->isValid()) {
    		$file = $show->getImage();
    		if ($file) {
                // Handling file upload
    			$filename = md5(uniqid()).'.'.$file->guessExtension();
    			$webRoot = $this->get('kernel')->getRootDir().'/../web';

    			$file->move($webRoot . '/uploads', $filename);
    			$show->setImage($filename);
    		}

    		$em = $this->get('doctrine')->getManager();
    		$em->persist($show);
    		$em->flush();
    		$success = true;
    	}

        return [
        	'form' => $form->createView(),
        	'success' => $success
        ];
    }

    /**
     * @Route("/addSeason/{id}", name="admin_add_season")
     */
    public function addSeasonAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:TVShow');
        
        if ($show = $repo->find($id)) {
            $season = new Season;
            $season
                ->setShow($show)
                ->setNumber(count($show->getSeasons())+1)
                ;
            $em->persist($season);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('show', ['id' => $id]));
    }

    /**
     * @Route("/deleteSeason/{id}", name="admin_delete_season")
     */
    public function deleteSeasonAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Season');       
        $repoEpisode = $em->getRepository('AppBundle:Episode');       
        
        if ($season  = $repo->find($id)) {
            $show_id = $season->getShow()->getId();
            
            $query = $em->createQuery(
            'SELECT e.id
            FROM AppBundle:Episode e
            WHERE e.season = :idSeason'
            )->setParameter('idSeason', $id);
            $episodes = $query->getResult();
            for($i =0; $i<count($episodes);$i++){
                $idEpisode = $episodes[$i]['id'];
                $episode = $repoEpisode->find($idEpisode);
                $em->remove($episode);
            }
            $em->remove($season);
            $em->flush();
            return $this->redirect($this->generateUrl('show', ['id' => $show_id]));
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }
      /**
     * @Route("/deleteEpisode/{id}", name="admin_delete_episode")
     */
    public function deleteEpisodeAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Episode');
        if ($episode = $repo->find($id)) {
            $id = $episode->getSeason()->getShow()->getId();
            $em->remove($episode);
            $em->flush();
            return $this->redirect($this->generateUrl('show', ['id' => $id]));
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }

    /**
     * @Route("/addEpisode/{id}", name="admin_add_episode")
     * @Template()
     */
    public function addEpisodeAction($id, Request $request)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Season');

        if ($season = $repo->find($id)) {
            $episode = new Episode;
            $episode
                ->setSeason($season)
                ->setNumber(count($season->getEpisodes())+1)
                ;

            $form = $this->createForm(EpisodeType::class, $episode);

            $form->handleRequest($request);
        	if ($form->isSubmitted() && $form->isValid()) {
                $em->persist($episode);
                $em->flush();
                return $this->redirect($this->generateUrl('show',[
                    'id' => $episode->getSeason()->getShow()->getId()
                ]));
            }
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/omdb", name="admin_omdb")
     * @Template()
     */
    public function omdbAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('keyword')
            ->getForm()
            ;

        $result = [];
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $omdb = new OMDbAPI();
            $result = $omdb->search(($data['keyword']),'series');
            $result = $result->data->Search;
        }

        return [
            'form' => $form->createView(),
            'result' => $result
        ];
    }
    /**
     * @Route("/omdb/{t}", name="admin_add_omdb")
     * @Template()
     */
    public function admin_add_omdb($t)
    {
        $omdb = new OMDbAPI();
        $serie = $omdb->fetch('t', $t);
//        $serieJson = json_decode($serie, true);
//        var_dump($serie->data);
//        var_dump($serie->data->Title);
//        var_dump($serie->data->Plot);
//        var_dump($serie->data->Poster);
//        var_dump($serie->data->totalSeasons);
//        var_dump($serie->data->seasons);
        
        
        /*Create show*/
        $show = new TVShow;
        $show
            ->setName($serie->data->Title)
            ->setSynopsis($serie->data->Plot);

        
        if ($serie->data->Poster) {
            $ext = explode(".", $serie->data->Poster);
            $ext = $ext[count($ext)-1];

            // Handling file upload
            $filename = md5(uniqid()).'.'.$ext;
            $webRoot = $this->get('kernel')->getRootDir().'/../web';

            copy($serie->data->Poster, $webRoot.'/uploads/'.$filename);
            $show->setImage($filename);
            
        }
//        $file = $serie->data->Poster;
//        if ($file) {
//            // Handling file upload
//            $filename = md5(uniqid()).'.'.$file->guessExtension();
//            $webRoot = $this->get('kernel')->getRootDir().'/../web';
//
//            $file->move($webRoot . '/uploads', $filename);
//            $show->setImage($filename);
//        }
        /*Create season*/
        
        
        $em = $this->get('doctrine')->getManager();
        $em->persist($show);
        $em->flush();
        
        return $this->redirect($this->generateUrl('shows'));
        
    }
    
}