<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190805164109 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, archetype_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, project_id VARCHAR(16) NOT NULL, region VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE732C6CC7 ON project (archetype_id)');
        $this->addSql('CREATE TABLE archetype (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, git_uri VARCHAR(255) NOT NULL, update_branch VARCHAR(255) NOT NULL, update_operation VARCHAR(255) NOT NULL)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE archetype');
    }
}
