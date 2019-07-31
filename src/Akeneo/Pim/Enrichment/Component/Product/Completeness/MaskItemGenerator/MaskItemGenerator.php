<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Completeness\MaskItemGenerator;

/**
 * @author    Pierre Allard <pierre.allard@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MaskItemGenerator
{
    /** @var MaskItemGeneratorForAttributeType[] */
    private $generators;

    public function __construct()
    {
        $this->generators = [];
    }

    public function generate(
        string $attributeCode,
        string $attributeType,
        string $channelCode,
        string $localeCode,
        $value
    ): array {
        return $this->getGenerator($attributeType)->forRawValue($attributeCode, $channelCode, $localeCode, $value);
    }

    private function getGenerator(string $attributeType): MaskItemGeneratorForAttributeType
    {
        if (!array_key_exists($attributeType, $this->generators)) {
            throw new \LogicException(sprintf('MaskItemGenerator for attribute type "%s" not found', $attributeType));
        }

        return $this->generators[$attributeType];
    }

    public function addGenerator(MaskItemGeneratorForAttributeType $generator)
    {
        foreach ($generator->supportedAttributeTypes() as $attributeType) {
            $this->generators[$attributeType] = $generator;
        }
    }
}
