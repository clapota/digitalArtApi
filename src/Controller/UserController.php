<?php

namespace App\Controller;

use App\Entity\User;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private function editUser(Request $request, $id)
    {
        try {
            $em = $this->getDoctrine()->getManager();
            $userRepository = $em->getRepository(User::class);

            $data = json_decode($request->getContent(), true);
            $user = $userRepository->find($id);
            if ($user === null) {
                $user = $userRepository->findOneBy(['base64' => $id]);
                if ($user === null)
                    return new JsonResponse(['error' => 'The requested user does not exist']);
            }
            if (isset($data['firstname']))
                $user->setFirstname($data['firstname']);
            if (isset($data['lastname']))
                $user->setLastname($data['lastname']);
            if (isset($data['base64']))
                $user->setBase64($data['base64']);
            $em->persist($user);
            $em->flush();
            return new JsonResponse(['success' => true, 'user' => $user->serialize()]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function getUserInfo($id)
    {
        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository(User::class);

        $user = $userRepository->find($id);
        if ($user === null) {
            $user = $userRepository->findOneBy(['base64' => $id]);
            if ($user === null)
                return new JsonResponse(['error' => 'The requested user does not exist'], 404);
        }
        return new JsonResponse(['success' => true,  'user' => $user->serialize()], 200);
    }

    /**
     * @Route("/user/{id}/folio", name="user_folio_add", methods={"POST"})
     * @param Request $request
     * @param KernelInterface $kernel
     * @param $id
     * @return JsonResponse
     */
    public function addFolio(Request $request, KernelInterface $kernel, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->find($id);
        if ($user == null) {
            $user = $userRepository->findOneBy(['base64' => $id]);
            if ($user == null)
                return new JsonResponse(['error' => 'The requested user does not exist'], 404);
        }
        if (!$request->files->get('folio_file')) {
            return new JsonResponse(['error' => 'missing parameter'], 500);
        }
        try {
            $this->processFolio($request->files->get('folio_file'), $kernel->getProjectDir() . '/public/' . $user->getPortfolio(). '/');
            return new JsonResponse(['success', true], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/user/{id}", name="user_id", methods={"GET", "PUT"})
     * @param Request $request
     * @param KernelInterface $kernel
     * @param $id
     * @return JsonResponse
     */
    public function user_id(Request $request, $id)
    {
        if ($request->getMethod() == 'PUT')
            return $this->editUser($request, $id);
        elseif ($request->getMethod() == 'GET')
            return $this->getUserInfo($id);
        else
            return new JsonResponse(['error' => 't\'as fait du sale frere'], 500);
    }

    /**
     * @Route("/user", name="user", methods={"GET", "POST"})
     * @param Request $request
     * @param KernelInterface $kernel
     * @return JsonResponse
     */
    public function index(Request $request, KernelInterface $kernel)
    {
        if ($request->getMethod() === 'POST')
            return $this->addUser($request, $kernel);
        $em = $this->getDoctrine()->getManager();
        $userRepository = $em->getRepository(User::class);

        $users = $userRepository->findAll();

        return new JsonResponse($users, 200);
    }

    /***
     * @param UploadedFile $file
     * @param $path
     * @return string
     * @throws Exception
     */
    private function processPhoto(UploadedFile $file, $path)
    {
        if (!$file->isValid()) {
            throw new Exception('Fail during upload');
        }
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $name = md5(uniqid()) . '.' . $ext;
        $file->move($path.'/public/', $name);
        return $name;
    }

    /***
     * @param Request $request
     * @param KernelInterface $kernel
     * @return JsonResponse
     */
    private function addUser(Request $request, KernelInterface $kernel)
    {
        $em = $this->getDoctrine()->getManager();
        $data = $request->request;
        $response = new JsonResponse();
        if (!$data->get('firstname') or !$data->get('lastname') or !$request->files->get('photo')) {
            $response->setStatusCode(500);
            $response->setData(['error' => 'Missing parameter in form-data']);
            return $response;
        }
        try {
            $user = new User();

            $user->setFirstname($data->get('firstname'));
            $user->setLastname($data->get('lastname'));
            if ($data->get('base64'))
                $user->setBase64($data->get('base64'));
            $photoPath = $this->processPhoto($request->files->get('photo'), $kernel->getProjectDir());
            $user->setPicture($photoPath);
            $user->setPortfolio($this->createFolio($kernel->getProjectDir()));
            $em->persist($user);
            $em->flush();
            $response->setStatusCode(200);
            $response->setData(['success' => true, 'user' => $user->serialize()]);
            return $response;
        } catch (Exception $e) {
            $response->setStatusCode(500);
            $response->setData(['error' => $e->getMessage()]);
            return $response;
        }
    }

    /***
     * @param UploadedFile $file
     * @param $path
     * @return string
     * @throws Exception
     */
    private function processFolio(UploadedFile $file, $path)
    {
        if (!$file->isValid()) {
            throw new Exception('Fail during upload');
        }
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $name = $file->getClientOriginalName();
        $file->move($path . '/', $file->getClientOriginalName());
        return $name;
    }

    private function createFolio($path)
    {
        $name = md5(uniqid(""));
        mkdir($path.'/public/'.$name);
        return $name;
    }
}