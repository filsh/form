<?php

declare(strict_types=1);

namespace Yiisoft\Form\Tests;

use InvalidArgumentException;
use Yiisoft\Form\FormModel;
use Yiisoft\Form\Tests\Stub\LoginForm;
use Yiisoft\Form\Tests\Stub\ValidatorMock;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\ValidatorInterface;
use function str_repeat;

require __DIR__ . '/Stub/NonNamespacedForm.php';

final class FormModelTest extends TestCase
{
    public function testAnonymousFormName(): void
    {
        $form = new class() extends FormModel {};
        $this->assertEquals('', $form->formName());
    }

    public function testDefaultFormName(): void
    {
        $form = new DefaultFormNameForm();
        $this->assertEquals('DefaultFormNameForm', $form->formName());
    }

    public function testNonNamespacedFormName(): void
    {
        $form = new \NonNamespacedForm();
        $this->assertEquals('NonNamespacedForm', $form->formName());
    }

    public function testCustomFormName(): void
    {
        $form = new CustomFormNameForm();
        $this->assertEquals('my-best-form-name', $form->formName());
    }

    public function testUnknownPropertyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(sprintf(
            '/You must specify the type hint for "%s" property in "([^"]+)" class./',
            'property',
        ));
        $form = new class() extends FormModel {
            private $property;
        };
    }

    public function testGetAttributeValue(): void
    {
        $form = new LoginForm();

        $form->login('admin');
        $this->assertEquals('admin', $form->getAttributeValue('login'));

        $form->password('123456');
        $this->assertEquals('123456', $form->getAttributeValue('password'));

        $form->rememberMe(true);
        $this->assertEquals(true, $form->getAttributeValue('rememberMe'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined property: "Yiisoft\Form\Tests\Stub\LoginForm::noExist".');
        $form->getAttributeValue('noExist');
    }

    public function testGetAttributeValueWithNestedAttribute(): void
    {
        $form = new FormWithNestedAttribute();

        $form->setUserLogin('admin');
        $this->assertEquals('admin', $form->getAttributeValue('user.login'));
    }

    public function testGetAttributeHint(): void
    {
        $form = new LoginForm();

        $this->assertEquals('Write your id or email.', $form->attributeHint('login'));
        $this->assertEquals('Write your password.', $form->attributeHint('password'));
        $this->assertEmpty($form->attributeHint('noExist'));
    }

    public function testGetNestedAttributeHint(): void
    {
        $form = new FormWithNestedAttribute();

        $this->assertEquals('Write your id or email.', $form->attributeHint('user.login'));
    }

    public function testGetAttributeLabel(): void
    {
        $form = new LoginForm();

        $this->assertEquals('Login:', $form->attributeLabel('login'));
        $this->assertEquals('Testme', $form->attributeLabel('testme'));
    }

    public function testGetNestedAttributeLabel(): void
    {
        $form = new FormWithNestedAttribute();

        $this->assertEquals('Login:', $form->attributeLabel('user.login'));
    }

    public function testAttributesLabels(): void
    {
        $form = new LoginForm();

        $expected = [
            'login' => 'Login:',
            'password' => 'Password:',
            'rememberMe' => 'remember Me:',
        ];

        $this->assertEquals($expected, $form->attributeLabels());
    }

    public function testErrorSummary(): void
    {
        $form = new LoginForm();

        $data = [
            'LoginForm' => [
                'login' => 'admin@.com',
                'password' => '123456',
            ],
        ];

        $expected = [
            'login' => 'This value is not a valid email address.',
            'password' => 'Is too short.',
        ];

        $this->assertTrue($form->load($data));
        $this->assertFalse($form->validate($this->createValidatorMock()));

        $this->assertEquals(
            $expected,
            $form->errorSummary(false)
        );

        $expected = [
            'This value is not a valid email address.',
            'Is too short.',
        ];

        $this->assertEquals(
            $expected,
            $form->errorSummary(true)
        );
    }

    public function testHasAttribute(): void
    {
        $form = new LoginForm();

        $this->assertTrue($form->hasAttribute('login'));
        $this->assertTrue($form->hasAttribute('password'));
        $this->assertTrue($form->hasAttribute('rememberMe'));
        $this->assertFalse($form->hasAttribute('noExist'));
        $this->assertFalse($form->hasAttribute('extraField'));
    }

    public function testLoad(): void
    {
        $form = new LoginForm();

        $this->assertNull($form->getLogin());
        $this->assertNull($form->getPassword());
        $this->assertFalse($form->getRememberMe());

        $data = [
            'LoginForm' => [
                'login' => 'admin',
                'password' => '123456',
                'rememberMe' => true,
                'noExist' => 'noExist',
            ],
        ];

        $this->assertTrue($form->load($data));

        $this->assertEquals('admin', $form->getLogin());
        $this->assertEquals('123456', $form->getPassword());
        $this->assertEquals(true, $form->getRememberMe());
    }

    public function testLoadWithNestedAttribute(): void
    {
        $form = new FormWithNestedAttribute();

        $data = [
            'FormWithNestedAttribute' => [
                'user.login' => 'admin',
            ],
        ];

        $this->assertTrue($form->load($data));
        $this->assertEquals('admin', $form->getUserLogin());
    }

    public function testFailedLoadForm(): void
    {
        $form1 = new LoginForm();
        $form2 = new class() extends FormModel {
        };

        $data1 = [
            'LoginForm2' => [
                'login' => 'admin',
                'password' => '123456',
                'rememberMe' => true,
                'noExist' => 'noExist',
            ],
        ];
        $data2 = [];

        $this->assertFalse($form1->load($data1));
        $this->assertFalse($form1->load($data2));

        $this->assertTrue($form2->load($data1));
        $this->assertFalse($form2->load($data2));
    }

    public function testLoadWithEmptyScope()
    {
        $form = new class() extends FormModel {
            private int $int = 1;
            private string $string = 'string';
            private float $float = 3.14;
            private bool $bool = true;
        };
        $form->load([
            'int' => '2',
            'float' => '3.15',
            'bool' => 'false',
            'string' => 555,
        ], '');
        $this->assertIsInt($form->getAttributeValue('int'));
        $this->assertIsFloat($form->getAttributeValue('float'));
        $this->assertIsBool($form->getAttributeValue('bool'));
        $this->assertIsString($form->getAttributeValue('string'));
    }

    public function testAddError(): void
    {
        $form = new LoginForm();
        $errorMessage = 'Invalid password.';

        $form->addError('password', $errorMessage);

        $this->assertTrue($form->hasErrors());
        $this->assertEquals($errorMessage, $form->firstError('password'));
    }

    public function testAddAndGetErrorForNonExistingAttribute(): void
    {
        $form = new LoginForm();
        $errorMessage = 'Invalid username and/or password.';

        $form->addError('form', $errorMessage);

        $this->assertTrue($form->hasErrors());
        $this->assertEquals($errorMessage, $form->firstError('form'));
    }

    public function testValidatorRules(): void
    {
        $form = new LoginForm();

        $form->login('');
        $form->validate($this->createValidatorMock());

        $this->assertEquals(
            ['Value cannot be blank.'],
            $form->error('login')
        );

        $form->login('x');
        $form->validate($this->createValidatorMock());
        $this->assertEquals(
            ['Is too short.'],
            $form->error('login')
        );

        $form->login(str_repeat('x', 60));
        $form->validate($this->createValidatorMock());
        $this->assertEquals(
            'Is too long.',
            $form->firstError('login')
        );

        $form->login('admin@.com');
        $form->validate($this->createValidatorMock());
        $this->assertEquals(
            'This value is not a valid email address.',
            $form->firstError('login')
        );
    }

    private function createValidatorMock(): ValidatorInterface
    {
        return new ValidatorMock();
    }
}

final class DefaultFormNameForm extends FormModel
{
}

final class CustomFormNameForm extends FormModel
{
    public function formName(): string
    {
        return 'my-best-form-name';
    }
}

final class FormWithNestedAttribute extends FormModel
{
    private ?int $id = null;
    private ?LoginForm $user = null;

    public function __construct()
    {
        $this->user = new LoginForm();
        parent::__construct();
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'id' => 'Readonly ID',
        ];
    }

    public function rules(): array
    {
        return [
            'id' => new Required(),
        ];
    }

    public function setUserLogin(string $login): void
    {
        $this->user->login('admin');
    }

    public function getUserLogin(): ?string
    {
        return $this->user->getLogin();
    }
}
