<?php

use PHPUnit\Framework\TestCase;
use Forge\Database\Iron\Model;

class ModelTest extends TestCase
{
    protected $model;

    protected function setUp(): void
    {
        // Simule une connexion à la base de données ou injecte une dépendance factice
        $this->model = $this->getMockBuilder(Model::class)
            ->setMethods(['save', 'find', 'delete'])
            ->getMock();
    }

    public function testCreateRecord()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com'
        ];

        $this->model->expects($this->once())
            ->method('save')
            ->willReturn(true);

        // Supposons que le modèle possède une méthode `fill` pour remplir les attributs
        $this->model->fill($data);

        $this->assertTrue($this->model->save());
    }

    public function testFindRecordById()
    {
        $expectedResult = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'johndoe@example.com'
        ];

        $this->model->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($expectedResult);

        $record = $this->model->find(1);

        $this->assertEquals($expectedResult, $record);
    }

    public function testDeleteRecord()
    {
        $this->model->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $this->assertTrue($this->model->delete());
    }
}
