<?php
declare(strict_types=1);

namespace App\Form\Type;

use App\PlatformClient;
use Platformsh\Client\Model\Region;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegionType extends AbstractType
{
    /**
     * @var PlatformClient
     */
    protected $client;

    public function __construct(PlatformClient $client)
    {
        $this->client = $client;
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_translation_domain' => false,
            'choices' => $this->getRegionOptions(),
        ]);
    }

    protected function getRegionOptions() : array
    {
        $values = $this->client->getRegions();

        $available = array_filter($values, function (Region $region) {
            return $region->available && !$region->private;
        });

        $choices = [];
        /** @var Region $region */
        foreach ($available as $region) {
            $choices[$region->label] = $region->id;
        }

        return $choices;
    }
}
