<?php

namespace Gedi\AdminBundle\Controller;

use Doctrine\Common\Annotations\AnnotationException;
use Exception;
use Gedi\BaseBundle\Entity\Document;
use Gedi\BaseBundle\Entity\Groupe;
use Gedi\BaseBundle\Entity\Projet;
use Gedi\BaseBundle\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;


class AdminController extends Controller
{
    /**
     * @Route("/home_admin")
     */
    public function homeAction()
    {
        $em = $this->getDoctrine()->getManager();
        // importation de tous les utilisateurs
        $tab_users = $em->getRepository('GediBaseBundle:Utilisateur')->findAll();
        // importation de tous les projets
        $tab_projects = $em->getRepository('GediBaseBundle:Projet')->findAll();
        // importation de tous les groupes
        $tab_groups = $em->getRepository('GediBaseBundle:Groupe')->findAll();
        // importation de tous les documents
        $tab_docs = $em->getRepository('GediBaseBundle:Document')->findAll();

        return $this->render('GediAdminBundle:Admin:home_admin.html.twig', array(
            'tab_users' => $tab_users,
            'tab_projects' => $tab_projects,
            'tab_groups' => $tab_groups,
            'tab_docs' => $tab_docs,
        ));
    }

    /**
     * Page d'administration des utilisateurs de l'application
     * @Route("/users_admin")
     * @param Request $request
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws Exception
     */
    public function usersAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $objet = new Utilisateur();
        $form = $this->createForm('Gedi\BaseBundle\Form\UtilisateurType', $objet);
        $form->handleRequest($request);

        if ($request->isMethod('POST') && isset($_POST['data']) && isset($_POST['typeaction'])) {
            $sel = $_POST['data'];

            if ($sel == null || $sel == "") {
                throw new Exception('La selection est nulle');
            }

            if ($_POST['typeaction'] == "supprimé") {
                // suppression
                for ($i = 0; $i <= count($sel) - 1; $i++) {
                    $toDel = $em->find('GediBaseBundle:Utilisateur', $sel[$i]['id']);
                    $em->remove($toDel);
                }
                $em->flush();
                $response = new JsonResponse();
                $response->setData(array('reponse' => "OK"));

            } else if ($_POST['typeaction'] == "enregistré" || $_POST['typeaction'] == "modifié") {
                // création ou modification
                $objet->setUsername($sel[1]['value']);
                $objet->setSalt(substr(md5(time()), 0, 23));
                $encoderFactory = $this->get('security.encoder_factory');
                $encoder = $encoderFactory->getEncoder($objet);
                $password = $encoder->encodePassword($sel[2]['value'], $objet->getSalt());
                $objet->setPassword($password);
                $objet->setNom($sel[4]['value']);
                $objet->setPrenom($sel[5]['value']);
                $objet->setActif(($sel[6]['value'] == "false") ? false : true);
                if ($sel[0]['value'] != "") {
                    $objet->setIdUtilisateur($sel[0]['value']);
                    $em->merge($objet);
                } else {
                    $em->persist($objet);
                }
                $em->flush();

                $rows = [
                    "ck" => 'data-checkbox="true"',
                    "id" => $objet->getIdUtilisateur(),
                    "nom" => $objet->getNom(),
                    "prenom" => $objet->getPrenom(),
                    "login" => $objet->getUsername(),
                    "datec" => date_format($objet->getDateCreation(), 'Y-m-d H:i:s'),
                    "datem" => date_format($objet->getDateModification(), 'Y-m-d H:i:s'),
                    "actif" => (($objet->getActif() == false) ? "" : "1"),
                    "ctrl" => '<span data-toggle="tooltip" data-placement="bottom" title="Accéder à l\'espace utilisateur">' .
                        '<button type="button" class="btn btn-default btn-primary round-button">' .
                        '<span class="glyphicon glyphicon-dashboard"></span></button></span>' .
                        '<span data-toggle="tooltip" data-placement="bottom" title="Editer la fiche utilisateur">' .
                        '<button type="button" class="btn btn-default btn-warning round-button" data-toggle="modal"' .
                        'data-target="#popup-add" onclick="edit(\'{&quot;idUtilisateur&quot;:' . $objet->getIdUtilisateur() .
                        ',&quot;username&quot;:&quot;' . $objet->getUsername() .
                        '&quot;,&quot;password&quot;:&quot;' . $objet->getPassword() .
                        '&quot;,&quot;nom&quot;:&quot;' . $objet->getNom() .
                        '&quot;,&quot;prenom&quot;:&quot;' . $objet->getPrenom() .
                        '&quot;,&quot;actif&quot;:' . (($objet->getActif() == false) ? "false" : "true") . '}\');">' .
                        '<span class="glyphicon glyphicon-pencil"></span></button></span>',
                ];

                $response = new JsonResponse();
                $response->setData(array('reponse' => (array)$rows));
            } else {
                throw new Exception('typeaction n\'est pas défini');
            }
            return $response;
        }

        // importation de tous les utilisateurs
        $tab_objets = $em->getRepository('GediBaseBundle:Utilisateur')->findAll();

        return $this->render('GediAdminBundle:Admin:users_admin.html.twig', array(
            'tab_objets' => $tab_objets,
            'utilisateur' => $objet,
            'utilisateurForm' => $form->createView(),
        ));
    }

    /**
     * Page d'administration des groupes de l'application
     * @Route("/groups_admin")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function groupsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $objet = new Groupe();
        $form = $this->createForm('Gedi\BaseBundle\Form\GroupeType', $objet);
        $form->handleRequest($request);

        if ($request->isMethod('POST') && isset($_POST['data']) && isset($_POST['typeaction'])) {
            $sel = $_POST['data'];

            if ($sel == null || $sel == "") {
                throw new Exception('La selection est nulle');
            }
            $rows = null;
            $response = new JsonResponse();

            if ($_POST['typeaction'] == "supprimé") {
                // suppression
                for ($i = 0; $i <= count($sel) - 1; $i++) {
                    $toDel = $em->find('GediBaseBundle:Groupe', $sel[$i]['id']);
                    $em->remove($toDel);
                }
                $em->flush();
                $response->setData(array('reponse' => "OK"));
            } else if ($_POST['typeaction'] == "enregistré" || $_POST['typeaction'] == "modifié") {
                if ($_POST['typeaction'] == "enregistré") {
                    // création
                    $objet->setNom($sel[1]['value']);
                    $utilisateur = $em->find('GediBaseBundle:Utilisateur', $sel[2]['value']);
                    $utilisateur->addIdGroupeUg($objet);
                    $objet->addIdUtilisateurUg($utilisateur);
                    $objet->setIdUtilisateurFkGroupe($utilisateur);
                    $em->persist($objet);

                } else if ($_POST['typeaction'] == "modifié") {
                    // modification
                    $objet = $em->find('GediBaseBundle:Groupe', $sel[0]['value']);
                    $objet->setNom($sel[1]['value']);
                    $em->merge($objet);
                }

                $em->flush();
                $rows = [
                    "ck" => 'data-checkbox="true"',
                    "id" => $objet->getIdGroupe(),
                    "nom" => $objet->getNom(),
                    "datec" => date_format($objet->getDateCreation(), 'Y-m-d H:i:s'),
                    "datem" => date_format($objet->getDateModification(), 'Y-m-d H:i:s'),
                    "propio" => $objet->getIdUtilisateurFkGroupe()->getNom() . " " . $objet->getIdUtilisateurFkGroupe()->getPrenom(),
                    "ctrl" => '<span data-toggle="tooltip" data-placement="bottom" title="Voir les membres">' .
                        '<button type="button" class="btn btn-default btn-primary round-button btn-view-entity"
                                data-toggle="modal" data-target="#popup-view">' .
                        '<span class="glyphicon glyphicon-user"></span></button></span>' .
                        '<span data-toggle="tooltip" data-placement="bottom" title="Editer le groupe">' .
                        '<button type="button" class="btn btn-default btn-warning round-button" data-toggle="modal"' .
                        'data-target="#popup-add" onclick="edit(\'{&quot;idGroupe&quot;:' . $objet->getIdGroupe() .
                        ',&quot;nom&quot;:&quot;' . $objet->getNom() . '&quot;}\');">' .
                        '<span class="glyphicon glyphicon-pencil"></span></button></span>',
                ];
                $response->setData(array('reponse' => (array)$rows));

            } else if ($_POST['typeaction'] == "children") {
                $objet = $em->find('GediBaseBundle:Groupe', $sel);
                $rows = [];
                foreach ($objet->getIdUtilisateurUg() as $child) {
                    array_push($rows, $child->getNom() . " " . $child->getPrenom() . " - " . $child->getUsername());
                }
                $response->setData(array('reponse' => (array)$rows));

            } else {
                throw new Exception('typeaction n\'est pas défini');
            }
            return $response;
        }

        // importation de tous les groupes
        $tab_objets = $em->getRepository('GediBaseBundle:Groupe')->findAll();
        // importation de tous les utilisateurs
        $tab_users = $em->getRepository('GediBaseBundle:Utilisateur')->findAll();

        return $this->render('GediAdminBundle:Admin:groups_admin.html.twig', array(
            'tab_objets' => $tab_objets,
            'groupe' => $objet,
            'groupeForm' => $form->createView(),
            'tab_users' => $tab_users,
        ));
    }

    /**
     * Page d'administration des projets de l'application
     * @Route("/projects_admin")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function projectsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $objet = new Projet();
        $form = $this->createForm('Gedi\BaseBundle\Form\ProjetType', $objet);
        $form->handleRequest($request);

        if ($request->isMethod('POST') && isset($_POST['data']) && isset($_POST['typeaction'])) {
            $sel = $_POST['data'];

            if ($sel == null || $sel == "") {
                throw new Exception('La selection est nulle');
            }
            $rows = null;
            $response = new JsonResponse();

            if ($_POST['typeaction'] == "supprimé") {
                // suppression
                for ($i = 0; $i <= count($sel) - 1; $i++) {
                    $toDel = $em->find('GediBaseBundle:Projet', $sel[$i]['id']);
                    $em->remove($toDel);
                }
                $em->flush();
                $response->setData(array('reponse' => "OK"));
            } else if ($_POST['typeaction'] == "enregistré" || $_POST['typeaction'] == "modifié") {
                if ($_POST['typeaction'] == "enregistré") {
                    // création
                    $objet->setNom($sel[1]['value']);
                    $utilisateur = $em->find('GediBaseBundle:Utilisateur', $sel[2]['value']);
                    $objet->setIdUtilisateurFkProjet($utilisateur);
                    $em->persist($objet);

                } else if ($_POST['typeaction'] == "modifié") {
                    // modification
                    $objet = $em->find('GediBaseBundle:Projet', $sel[0]['value']);
                    $objet->setNom($sel[1]['value']);
                    $em->merge($objet);
                }

                $em->flush();
                $rows = [
                    "ck" => 'data-checkbox="true"',
                    "id" => $objet->getIdProjet(),
                    "nom" => $objet->getNom(),
                    "datec" => date_format($objet->getDateCreation(), 'Y-m-d H:i:s'),
                    "datem" => date_format($objet->getDateModification(), 'Y-m-d H:i:s'),
                    "propio" => $objet->getIdUtilisateurFkProjet()->getNom() . " " . $objet->getIdUtilisateurFkProjet()->getPrenom(),
                    "ctrl" => '<span data-toggle="tooltip" data-placement="bottom" title="Voir les documents">' .
                        '<button type="button" class="btn btn-default btn-primary round-button btn-view-entity"
                                data-toggle="modal" data-target="#popup-view"">' .
                        '<span class="glyphicon glyphicon-user"></span></button></span>' .
                        '<span data-toggle="tooltip" data-placement="bottom" title="Editer le projet">' .
                        '<button type="button" class="btn btn-default btn-warning round-button" data-toggle="modal"' .
                        'data-target="#popup-add" onclick="edit(\'{&quot;idProjet   &quot;:' . $objet->getIdProjet() .
                        ',&quot;nom&quot;:&quot;' . $objet->getNom() . '&quot;}\');">' .
                        '<span class="glyphicon glyphicon-pencil"></span></button></span>',
                ];
                $response->setData(array('reponse' => (array)$rows));

            } else if ($_POST['typeaction'] == "children") {
                $objet = $em->find('GediBaseBundle:Projet', $sel);
                $rows = [];
                foreach ($objet->getIdProjetFkDocument() as $child) {
                    array_push($rows, $child->getNom() . " " . $child->getTypeDoc() . " - " . $child->getTag());
                }
                $response->setData(array('reponse' => (array)$rows));

            } else {
                throw new Exception('typeaction n\'est pas défini');
            }
            return $response;
        }

        // importation de tous les projets
        $tab_objets = $em->getRepository('GediBaseBundle:Projet')->findAll();
        // importation de tous les utilisateurs
        $tab_users = $em->getRepository('GediBaseBundle:Utilisateur')->findAll();

        return $this->render('GediAdminBundle:Admin:projects_admin.html.twig', array(
            'tab_objets' => $tab_objets,
            'projet' => $objet,
            'projetForm' => $form->createView(),
            'tab_users' => $tab_users,
        ));
    }

    /**
     * Page d'administration des documents de l'application
     * @Route("/docs_admin")
     * @param Request $request
     * @return Response
     */
    public function documentsAction(Request $request)
    {
        // importation de tous les documents
        $em = $this->getDoctrine()->getManager();
        $tab_objets = $em->getRepository('GediBaseBundle:Document')->findAll();

        // création du formulaire pour créer un nouveau document
        $document = new Document();
        $documentForm = $this->createForm('Gedi\BaseBundle\Form\DocumentType', $document);
        $documentForm->handleRequest($request);

        if ($documentForm->isSubmitted() && $documentForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($document);
            $em->flush();
            return $this->redirectToRoute('docs_admin');
        }

        return $this->render('GediAdminBundle:Admin:docs_admin.html.twig', array(
            'tab_objets' => $tab_objets,
            'document' => $document,
            'documentForm' => $documentForm->createView(),
        ));
    }
}
