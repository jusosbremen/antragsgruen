SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

DROP TABLE IF EXISTS `###TABLE_PREFIX###votingBlock`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###site`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultation`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motion`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###amendment`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###user`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###userNotification`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###amendmentAdminComment`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###amendmentComment`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###amendmentSupporter`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###amendmentSection`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###migration`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionAdminComment`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionSubscription`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionComment`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionCommentSupporter`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionSupporter`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionSection`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationText`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationAdmin`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationFile`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationSubscription`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###siteAdmin`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###emailLog`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###emailBlacklist`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationOdtTemplate`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationLatexTemplate`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationAgendaItem`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationTag`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationUserPrivilege`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###motionTag`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationSettingsMotionSection`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationMotionType`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationSettingsTag`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###consultationLog`;
DROP TABLE IF EXISTS `###TABLE_PREFIX###texTemplate`;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
