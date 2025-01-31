<?php

require_once 'ProgramFunctions/TipMessage.fnc.php';

$_REQUEST['table'] = issetVal( $_REQUEST['table'], '0' );
$_REQUEST['period'] = issetVal( $_REQUEST['period'] );

// Set date.
$date = RequestedDate( 'date', DBDate(), 'set' );

DrawHeader( ProgramTitle() );

$categories_RET = DBGet( "SELECT ID,TITLE
	FROM ATTENDANCE_CODE_CATEGORIES
	WHERE SYEAR='" . UserSyear() . "'
	AND SCHOOL_ID='" . UserSchool() . "'
	ORDER BY SORT_ORDER,TITLE" );

$category_select = "<select name=table onChange='ajaxPostForm(this.form,true);'><option value='0'" .
	( $_REQUEST['table'] == '0' ? ' selected' : '' ) . ">" . _( 'Attendance' ) . "</option>";

foreach ( (array) $categories_RET as $category )
{
	$category_select .= '<option value="' . AttrEscape( $category['ID'] ) . '"' .
		( ( $_REQUEST['table'] == $category['ID'] ) ? ' selected' : '' ) . ">" . $category['TITLE'] . "</option>";
}

$category_select .= "</select>";

$periods_RET = DBGet( "SELECT sp.PERIOD_ID,sp.TITLE
	FROM SCHOOL_PERIODS sp
	WHERE sp.SCHOOL_ID='" . UserSchool() . "'
	AND sp.SYEAR='" . UserSyear() . "'
	AND EXISTS (SELECT '' FROM COURSE_PERIODS
		WHERE SYEAR=sp.SYEAR
		AND PERIOD_ID=sp.PERIOD_ID
		AND position('," . $_REQUEST['table'] . ",' IN DOES_ATTENDANCE)>0)
	ORDER BY sp.SORT_ORDER", [], [ 'PERIOD_ID' ] );

$period_select = "<select name=period onChange='ajaxPostForm(this.form,true);'><option value=''>" . _( 'All' ) . "</option>";

foreach ( (array) $periods_RET as $id => $period )
{
	$period_select .= '<option value="' . AttrEscape( $id ) . '"' . ( ( $_REQUEST['period'] == $id ) ? ' selected' : '' ) . ">" . $period[1]['TITLE'] . "</option>";
}

$period_select .= "</select>";

echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname']  ) . '" method="GET">';
DrawHeader( PrepareDate( $date, '_date', false, [ 'submit' => true ] ) . ' - ' . $period_select );
DrawHeader( '', $category_select );
echo '</form>';

//FJ days numbered
//FJ multiple school periods for a course period

if ( SchoolInfo( 'NUMBER_DAYS_ROTATION' ) !== null )
{
	$sql = "SELECT s.STAFF_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,
	sp.TITLE,cpsp.PERIOD_ID,cp.TITLE AS CP_TITLE,
	(SELECT 'Y'
		FROM ATTENDANCE_COMPLETED ac
		WHERE ac.STAFF_ID=cp.TEACHER_ID
		AND ac.SCHOOL_DATE=acc.SCHOOL_DATE
		AND ac.PERIOD_ID=sp.PERIOD_ID
		AND TABLE_NAME='" . (int) $_REQUEST['table'] . "') AS COMPLETED
	FROM STAFF s,COURSE_PERIODS cp,SCHOOL_PERIODS sp,ATTENDANCE_CALENDAR acc,COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
	AND	sp.PERIOD_ID=cpsp.PERIOD_ID AND position('," . $_REQUEST['table'] . ",' IN cp.DOES_ATTENDANCE)>0
	AND cp.TEACHER_ID=s.STAFF_ID
	AND cp.MARKING_PERIOD_ID IN (" . GetAllMP( 'QTR', GetCurrentMP( 'QTR', $date ) ) . ")
	AND cp.SYEAR='" . UserSyear() . "'
	AND cp.SCHOOL_ID='" . UserSchool() . "'
	AND s.PROFILE='teacher'
	" . ( $_REQUEST['period'] ? " AND cpsp.PERIOD_ID='" . (int) $_REQUEST['period'] . "'" : '' ) . "
	AND acc.CALENDAR_ID=cp.CALENDAR_ID
	AND acc.SCHOOL_DATE='" . $date . "'
	AND acc.SYEAR='" . UserSyear() . "'
	AND (acc.MINUTES IS NOT NULL AND acc.MINUTES>0)
	AND (sp.BLOCK IS NULL
		AND position(substring('MTWHFSU' FROM cast((SELECT CASE COUNT(SCHOOL_DATE)%" . SchoolInfo( 'NUMBER_DAYS_ROTATION' ) . "
			WHEN 0 THEN " . SchoolInfo( 'NUMBER_DAYS_ROTATION' ) . "
			ELSE COUNT(SCHOOL_DATE)%" . SchoolInfo( 'NUMBER_DAYS_ROTATION' ) . " END AS day_number
			FROM ATTENDANCE_CALENDAR
			WHERE SCHOOL_DATE<=acc.SCHOOL_DATE
			AND SCHOOL_DATE>=(SELECT START_DATE
				FROM SCHOOL_MARKING_PERIODS
				WHERE START_DATE<=acc.SCHOOL_DATE
				AND END_DATE>=acc.SCHOOL_DATE
				AND MP='QTR'
				AND SCHOOL_ID=acc.SCHOOL_ID
				AND SYEAR=acc.SYEAR)
			AND CALENDAR_ID=cp.CALENDAR_ID)
		AS INT) FOR 1) IN cpsp.DAYS)>0 OR (sp.BLOCK IS NOT NULL AND sp.BLOCK=acc.BLOCK))
	ORDER BY FULL_NAME";
}
else
{
	$sql = "SELECT s.STAFF_ID," . DisplayNameSQL( 's' ) . " AS FULL_NAME,s.ROLLOVER_ID,
		sp.TITLE,cpsp.PERIOD_ID,cp.TITLE AS CP_TITLE,
		(SELECT 'Y'
			FROM ATTENDANCE_COMPLETED ac
			WHERE ac.STAFF_ID=cp.TEACHER_ID
			AND ac.SCHOOL_DATE=acc.SCHOOL_DATE
			AND ac.PERIOD_ID=sp.PERIOD_ID
			AND TABLE_NAME='" . (int) $_REQUEST['table'] . "') AS COMPLETED
		FROM STAFF s,COURSE_PERIODS cp,SCHOOL_PERIODS sp,ATTENDANCE_CALENDAR acc,
			COURSE_PERIOD_SCHOOL_PERIODS cpsp
		WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
		AND	sp.PERIOD_ID=cpsp.PERIOD_ID
		AND position('," . $_REQUEST['table'] . ",' IN cp.DOES_ATTENDANCE)>0
		AND cp.TEACHER_ID=s.STAFF_ID
		AND cp.MARKING_PERIOD_ID IN (" . GetAllMP( 'QTR', GetCurrentMP( 'QTR', $date ) ) . ")
		AND cp.SYEAR='" . UserSyear() . "'
		AND cp.SCHOOL_ID='" . UserSchool() . "'
		AND s.PROFILE='teacher'" .
	( $_REQUEST['period'] ? " AND cpsp.PERIOD_ID='" . (int) $_REQUEST['period'] . "'" : '' ) .
	" AND acc.CALENDAR_ID=cp.CALENDAR_ID
		AND acc.SCHOOL_DATE='" . $date . "'
		AND acc.SYEAR='" . UserSyear() . "'
		AND (acc.MINUTES IS NOT NULL AND acc.MINUTES>0)
		AND (sp.BLOCK IS NULL
			AND position(substring('UMTWHFS' FROM cast(extract(DOW FROM acc.SCHOOL_DATE) AS INT)+1 FOR 1) IN cpsp.DAYS)>0
			OR sp.BLOCK IS NOT NULL
			AND acc.BLOCK IS NOT NULL
			AND sp.BLOCK=acc.BLOCK)
		ORDER BY FULL_NAME";
}

$RET = DBGet( $sql, [ 'FULL_NAME' => 'makePhotoTipMessage' ], [ 'STAFF_ID' ] );

if ( ! isset( $_REQUEST['period'] )
	|| ! $_REQUEST['period'] )
{
	$i = 0;

	$staff_RET = [];

	foreach ( (array) $RET as $staff_id => $periods )
	{
		$i++;

		$staff_RET[$i]['FULL_NAME'] = $periods[1]['FULL_NAME'];

		foreach ( (array) $periods as $period )
		{
			if ( isset( $_REQUEST['_ROSARIO_PDF'] ) )
			{
				$staff_RET[$i][$period['PERIOD_ID']] = ( $period['COMPLETED'] === 'Y' ?
					_( 'Yes' ) : _( 'No' ) );

				continue;
			}

			$staff_RET[$i][$period['PERIOD_ID']] = MakeTipMessage(
				$period['CP_TITLE'],
				_( 'Course Title' ),
				button( $period['COMPLETED'] === 'Y' ? 'check' : 'x' )
			);
		}
	}

	$columns = [ 'FULL_NAME' => _( 'Teacher' ) ];

	foreach ( (array) $periods_RET as $id => $period )
	{
		$columns[$id] = $period[1]['TITLE'];
	}

	ListOutput( $staff_RET, $columns, 'Teacher who takes attendance', 'Teachers who take attendance' );
}
else
{
	$period_title = $periods_RET[$_REQUEST['period']][1]['TITLE'];

	// FJ display icon for completed column.

	foreach ( (array) $RET as $staff_id => $periods )
	{
		foreach ( (array) $periods as $id => $period )
		{
			$RET[$staff_id][$id]['COMPLETED'] = button( $period['COMPLETED'] === 'Y' ? 'check' : 'x' );
		}
	}

	ListOutput(
		$RET,
		[
			'FULL_NAME' => _( 'Teacher' ),
			'CP_TITLE' => _( 'Course Period' ),
			'COMPLETED' => _( 'Completed' ),
		],
		sprintf( _( 'Teacher who takes %s attendance' ), $period_title ),
		sprintf( _( 'Teachers who take %s attendance' ), $period_title ),
		false,
		[ 'STAFF_ID' ]
	);
}
