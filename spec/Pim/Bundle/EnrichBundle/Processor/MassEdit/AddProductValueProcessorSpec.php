<?php

namespace spec\Pim\Bundle\EnrichBundle\Processor\MassEdit;

use Akeneo\Bundle\BatchBundle\Entity\JobExecution;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Updater\ProductUpdaterInterface;
use Pim\Bundle\EnrichBundle\Entity\MassEditJobConfiguration;
use Pim\Bundle\EnrichBundle\Entity\Repository\MassEditRepository;
use Prophecy\Argument;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ValidatorInterface;

class AddProductValueProcessorSpec extends ObjectBehavior
{
    function let(
        ProductUpdaterInterface $productUpdater,
        ValidatorInterface $validator,
        MassEditRepository $massEditRepository
    ) {
        $this->beConstructedWith(
            $productUpdater,
            $validator,
            $massEditRepository
        );
    }

    function it_adds_values_to_product(
        $productUpdater,
        $validator,
        ProductInterface $product,
        StepExecution $stepExecution,
        MassEditRepository $massEditRepository,
        JobExecution $jobExecution,
        MassEditJobConfiguration $massEditJobConf
    ) {
        $violations = new ConstraintViolationList([]);
        $validator->validate($product)->willReturn($violations);

        $stepExecution->getJobExecution()->willReturn($jobExecution);

        $massEditRepository->findOneBy(['jobExecution' => $jobExecution])->willReturn($massEditJobConf);
        $massEditJobConf->getConfiguration()->willReturn(
            json_encode(['filters' => [], 'actions' => [['field' => 'categories', 'value' => ['office', 'bedroom']]]])
        );

        $productUpdater->addData($product, 'categories', ['office', 'bedroom'])->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('mass_edited')->shouldBeCalled();

        $this->setStepExecution($stepExecution);

        $this->process($product);
    }

    function it_adds_invalid_values_to_product(
        $productUpdater,
        $validator,
        ProductInterface $product,
        StepExecution $stepExecution,
        MassEditRepository $massEditRepository,
        JobExecution $jobExecution,
        MassEditJobConfiguration $massEditJobConf
    ) {
        $violation = new ConstraintViolation('error2', 'spec', [], '', '', $product);
        $violations = new ConstraintViolationList([$violation, $violation]);
        $validator->validate($product)->willReturn($violations);

        $stepExecution->getJobExecution()->willReturn($jobExecution);

        $massEditRepository->findOneBy(['jobExecution' => $jobExecution])->willReturn($massEditJobConf);
        $massEditJobConf->getConfiguration()->willReturn(
            json_encode(['filters' => [], 'actions' => [['field' => 'categories', 'value' => ['office', 'bedroom']]]])
        );

        $productUpdater->addData($product, 'categories', ['office', 'bedroom'])->shouldBeCalled();
        $stepExecution->addWarning(Argument::cetera())->shouldBeCalled();
        $stepExecution->incrementSummaryInfo('skipped_products')->shouldBeCalled();

        $this->setStepExecution($stepExecution);

        $this->process($product);
    }

    function it_returns_the_configuration_fields()
    {
        $this->getConfigurationFields()->shouldReturn([]);
    }

    function it_sets_the_step_execution(StepExecution $stepExecution)
    {
        $this->setStepExecution($stepExecution)->shouldReturn($this);
    }
}
