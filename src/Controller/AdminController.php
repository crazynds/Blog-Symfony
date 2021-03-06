<?php



namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Author;
use App\Form\AuthorFormType;

use App\Entity\BlogPost;
use App\Form\EntryFormType;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $authorRepository;

    /** @var \Doctrine\Common\Persistence\ObjectRepository */
    private $blogPostRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->blogPostRepository = $entityManager->getRepository('App:BlogPost');
        $this->authorRepository = $entityManager->getRepository('App:Author');
    }


    /**
     * @Route("/author/create", name="author_create")
     */
    public function createAuthorAction(Request $request){
        if ($this->authorRepository->findOneByUsername($this->getUser()->getUserName())) {
            // Redirect to dashboard.
            $this->addFlash('error', 'Não foi possivel criar o autor, esse username já está em uso!');

            return $this->redirectToRoute('homepage');
        }

        $author = new Author();
        $author->setUsername($this->getUser()->getUserName());

        $form = $this->createForm(AuthorFormType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($author);
            $this->entityManager->flush($author);

            $request->getSession()->set('user_is_author', true);
            $this->addFlash('success', 'Parabens! Você é um autor agora.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('admin/create_author.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/create-entry", name="admin_create_entry")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createEntryAction(Request $request){
        $blogPost = new BlogPost();

        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());
        $blogPost->setAuthor($author);

        $form = $this->createForm(EntryFormType::class, $blogPost);
        $form->handleRequest($request);

        if($form->isSubmitted())
            $blogPost2 = $this->blogPostRepository->findOneBySlug($request->get('entry_form')['slug']);
        else
            $blogPost2 = false;
        // Check is valid
        if ($form->isSubmitted() && $form->isValid() && !$blogPost2) {
            $this->entityManager->persist($blogPost);
            $this->entityManager->flush($blogPost);

            $this->addFlash('success', 'Parabens! Sua postagem foi criada.');

            return $this->redirectToRoute('admin_entries');
        }


        return $this->render('admin/entry_form.html.twig', [
            'form' => $form->createView(),
            'slug_exist' => ($blogPost2)?true:false
        ]);
    }
    /**
     * @Route("/", name="admin_index")
     * @Route("/entries", name="admin_entries")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function entriesAction()
    {
        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());

        $blogPosts = [];

        if ($author) {
            $blogPosts = $this->blogPostRepository->findByAuthor($author);
        }

        return $this->render('admin/entries.html.twig', [
            'blogPosts' => $blogPosts
        ]);
    }

    /**
     * @Route("/delete-entry/{entryId}", name="admin_delete_entry")
     *
     * @param $entryId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteEntryAction($entryId)
    {
        $blogPost = $this->blogPostRepository->findOneById($entryId);
        $author = $this->authorRepository->findOneByUsername($this->getUser()->getUserName());

        if (!$blogPost || $author !== $blogPost->getAuthor()) {
            $this->addFlash('error', 'Não é possivel remover essa postagem!');

            return $this->redirectToRoute('admin_entries');
        }

        $this->entityManager->remove($blogPost);
        $this->entityManager->flush();

        $this->addFlash('success', 'Postagem removida!');

        return $this->redirectToRoute('admin_entries');
    }
}
