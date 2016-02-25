<?php

/**
 * This file is part of the RCHJWTUserBundle package.
 *
 * Robin Chalas <robin.chalas@gmail.com>
 *
 * For more informations about license, please see the LICENSE
 * file distributed in this source code.
 */
namespace RCH\JWTUserBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Generates RSA Keys for LexikJWT.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class GenerateKeysCommand extends ContainerAwareCommand
{
    use OutputHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('rch:jwt:generate-keys')
          ->setDescription('Generate RSA keys used by LexikJWTAuthenticationBundle');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sayWelcome($output);
        $kernelRootDir = $this->getContainer()->getParameter('kernel.root_dir');
        $question = new Question('Choose the passphrase of your private RSA key : ');

        $questionHelper = $this->getHelper('question');
        $passphrase = $questionHelper->ask($input, $output, $question);

        if (!$passphrase) {
            $passphrase = random_bytes(10);
        }

        if (is_writable($kernelRootDir.'/../var')) {
            $path = $kernelRootDir.'/../var/jwt';
        } else {
            $path = $kernelRootDir.'/var/jwt';
        }

        $fs = new FileSystem();
        $fs->mkdir($path);

        $this->generatePrivateKey($path, $passphrase, $output);
        $this->generatePublicKey($path, $passphrase, $output);

        $output->writeln(sprintf('<info>RSA keys successfully generated with passphrase <comment>%s</comment></info>', $passphrase));
    }

    /**
     * Generate a RSA private key.
     *
     * @param string          $path
     * @param string          $passphrase
     * @param OutputInterface $output
     *
     * @throws ProcessFailedException
     */
    protected function generatePrivateKey($path, $passphrase, OutputInterface $output)
    {
        $processArgs = sprintf('genrsa -out %s/private.pem  -aes256 -passout pass:%s 4096', $path, $passphrase);

        $this->generateKey($processArgs, $output);
    }

    /**
     * Generate a RSA public key.
     *
     * @param string          $path
     * @param string          $passphrase
     * @param OutputInterface $output
     */
    protected function generatePublicKey($path, $passphrase, OutputInterface $output)
    {
        $processArgs = sprintf('rsa -pubout -in %s/private.pem -out %s/public.pem -passin pass:%s', $path, $path, $passphrase);

        $this->generateKey($processArgs, $output);
    }

    /**
     * Generate a RSA key.
     *
     * @param string          $processArgs
     * @param Outputinterface $output
     *
     * @throws ProcessFailedException
     */
    protected function generateKey($processArgs, OutputInterface $output)
    {
        $process = new Process(sprintf('openssl %s', $processArgs));
        $process->setTimeout(3600);

        $process->run(function ($type, $buffer) use ($output) {
            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->write($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process->getExitCode();
    }
}
