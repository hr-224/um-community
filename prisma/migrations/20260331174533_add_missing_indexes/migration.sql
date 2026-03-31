-- Add missing indexes identified in code review
-- All changes below were already applied to the database via `prisma db push` during development.
-- This migration file records them so Prisma migrate history stays in sync.

-- CommunityMember: explicit userId index
CREATE INDEX `CommunityMember_userId_idx` ON `CommunityMember`(`userId`);

-- Rank: explicit departmentId index
ALTER TABLE `Rank` DROP FOREIGN KEY `Rank_departmentId_fkey`;
CREATE INDEX `Rank_departmentId_idx` ON `Rank`(`departmentId`);
ALTER TABLE `Rank` ADD CONSTRAINT `Rank_departmentId_fkey` FOREIGN KEY (`departmentId`) REFERENCES `Department`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- LOA: memberId index
CREATE INDEX `LOA_memberId_idx` ON `LOA`(`memberId`);

-- Transfer: memberId index
CREATE INDEX `Transfer_memberId_idx` ON `Transfer`(`memberId`);

-- Application: applicantUserId index
CREATE INDEX `Application_applicantUserId_idx` ON `Application`(`applicantUserId`);

-- Account: explicit userId index
CREATE INDEX `Account_userId_idx` ON `Account`(`userId`);

-- Department: description column promoted to TEXT
ALTER TABLE `Department` MODIFY COLUMN `description` TEXT NULL;

-- InviteLink: remove redundant code index (covered by UNIQUE constraint)
DROP INDEX `InviteLink_code_idx` ON `InviteLink`;
