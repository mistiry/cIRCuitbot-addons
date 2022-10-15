<?php
function getQuote($data) {
    global $dbconnection;
    global $ircdata;
    global $setting;

    $search = $data['commandargs'];
    $search = mysqli_real_escape_string($dbconnection, $search);
    
    //they are probably searching a specific id if search term is numeric
    if(is_numeric($search)) {
        $query = "SELECT * FROM quotes WHERE id = $search LIMIT 1";
    } elseif(strlen($search)>1) {
        $query = "SELECT * FROM quotes WHERE quote LIKE '%$search%' ORDER BY rand() LIMIT 1";
    } else {
        $query = "SELECT * FROM quotes ORDER BY rand() LIMIT 1";
    }

    if(strlen($search) == 0) {
        $notnick = $data['usernickname'];
        $query = "SELECT * FROM quotes WHERE submittedby NOT LIKE '%".$notnick."%' AND downvotes < 3 ORDER BY rand() LIMIT 1";
    }

    $result = mysqli_query($dbconnection, $query);
    if(mysqli_num_rows($result)>0) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            $timestamp = $row['timestamp'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $message = "Quote #$id (U:$upvotes/D:$downvotes): $quote [submitted by $submittedby on $timestamp]";
        }
    } else {
        $message = "Unable to locate a quote matching the search term: $search";
    }
    sendPRIVMSG($data['location'],$message);  
    return true;
}

function addQuote($data) {
    global $dbconnection;
    global $timestamp;

    $quotetoadd = $data['commandargs'];
    $quotetoadd = mysqli_real_escape_string($dbconnection, $quotetoadd);
    $submittedby = $data['usernickname'];
    $query = "INSERT INTO quotes (submittedby,quote,timestamp,upvotes,downvotes) VALUES ('".$submittedby."','".$quotetoadd."','".$timestamp."',0,0)";
    if(mysqli_query($dbconnection, $query)) {
        $query = "SELECT id FROM quotes ORDER BY timestamp DESC LIMIT 1";
        $result = mysqli_query($dbconnection,$query);
        if(mysqli_num_rows($result) == 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $id = $row['id'];
                sendPRIVMSG($data['location'],"Quote #$id added!");
            }
        }
    } else {
        sendPRIVMSG($data['location'],"Failed to add quote.");
    }
    $quotetoadd = "";
    $query = "";
    $result = "";
    $id = "";
    return true;
}

function upvoteQuote($data) {
    global $dbconnection;

    $quoteid = trim($data['commandargs']);
    if(is_numeric($quoteid)) {
        $quoteid = mysqli_real_escape_string($dbconnection, $quoteid);
        $query = "SELECT id,upvotes,downvotes,voted_hostnames FROM quotes WHERE id = $quoteid LIMIT 1";
    } else {
        $message = "Invalid quote ID.";
        sendPRIVMSG($data['location'],$message);
        return true;
    }
    $result = mysqli_query($dbconnection,$query);
    $query = "";
    if(mysqli_num_rows($result)>0) {
        //do the thing
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $voted_hostnames = $row['voted_hostnames'];
            $votedhostnamesArray = unserialize($voted_hostnames);
            if(empty($votedhostnamesArray)) {
                $votedhostnamesArray = array();
            }
            if(is_array($votedhostnamesArray)) {
                if(in_array($data['userhostname'],$votedhostnamesArray)) {
                    $message = "You have already voted for quote #".$id."";
                    sendPRIVMSG($data['location'],$message);
                    return true;
                } else {
                    array_push($votedhostnamesArray,$data['userhostname']);
                    $newupvotes = $upvotes + 1;
                    $votedhostnames = serialize($votedhostnamesArray);
                    $query = "UPDATE quotes SET upvotes = $newupvotes, voted_hostnames = '".$votedhostnames."' WHERE id = $id LIMIT 1";
                }
            }
            
            if(mysqli_query($dbconnection,$query)) {
                $message = "".$data['usernickname'].": your upvote has been applied to quote #$id.";
            } else {
                $message = "Unable to apply vote. Please try again using !up [quote_id]";
            }
        }
    } else {
        $message = "Unable to apply vote. Please try again using !up [quote_id]";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function downvoteQuote($data) {
    global $dbconnection;

    $quoteid = trim($data['commandargs']);
    if(is_numeric($quoteid)) {
        $quoteid = mysqli_real_escape_string($dbconnection, $quoteid);
        $query = "SELECT id,upvotes,downvotes,voted_hostnames FROM quotes WHERE id = $quoteid LIMIT 1";
    } else {
        $message = "Invalid quote ID.";
        sendPRIVMSG($data['location'],$message);
        return true;
    }
    $result = mysqli_query($dbconnection,$query);
    $query = "";
    if(mysqli_num_rows($result)>0) {
        //do the thing
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $downvotes = $row['downvotes'];
            $voted_hostnames = $row['voted_hostnames'];
            $votedhostnamesArray = unserialize($voted_hostnames);
            if(empty($votedhostnamesArray)) {
                $votedhostnamesArray = array();
            }
            if(is_array($votedhostnamesArray)) {
                if(in_array($data['userhostname'],$votedhostnamesArray)) {
                    $message = "You have already voted for quote #".$id."";
                    sendPRIVMSG($data['location'],$message);
                    return true;
                } else {
                    array_push($votedhostnamesArray,$data['userhostname']);
                    $newdownvotes = $downvotes + 1;
                    $votedhostnames = serialize($votedhostnamesArray);
                    $query = "UPDATE quotes SET downvotes = $newdownvotes, voted_hostnames = '".$votedhostnames."' WHERE id = $id LIMIT 1";
                }
            }
            
            if(mysqli_query($dbconnection,$query)) {
                $message = "".$data['usernickname'].": your downvote has been applied to quote #$id.";
            } else {
                $message = "Unable to apply vote. Please try again using !down [quote_id]";
            }
        }
    } else {
        $message = "Unable to apply vote. Please try again using !down [quote_id]";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getTopQuote($data) {
    global $dbconnection;

    $query = "SELECT id,submittedby,quote,timestamp,upvotes,downvotes FROM quotes ORDER BY upvotes DESC LIMIT 1";
    $result = mysqli_query($dbconnection,$query);
    if(mysqli_num_rows($result) == 1) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            $timestamp = $row['timestamp'];
            $message = "Best Quote: #$id (U:".$upvotes."/D:".$downvotes.") - ".$quote."  [submitted by ".$submittedby." on ".$timestamp."]";
        }
    } else {
        $message = "Encountered an error polling for quote stats.";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getBottomQuote($data) {
    global $dbconnection;

    $query = "SELECT id,submittedby,quote,timestamp,upvotes,downvotes FROM quotes ORDER BY downvotes ASC LIMIT 1";
    $result = mysqli_query($dbconnection,$query);
    if(mysqli_num_rows($result) == 1) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            $timestamp = $row['timestamp'];
            $message = "Worst Quote: #$id (U:".$upvotes."/D:".$downvotes.") - ".$quote."  [submitted by ".$submittedby." on ".$timestamp."]";
        }
    } else {
        $message = "Encountered an error polling for quote stats.";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getQuoteStats($data) {
    global $dbconnection; 

    $countquery = "SELECT count(*) AS total FROM quotes";
    $totalsubmittersquery = "SELECT count(distinct submittedby) AS users FROM quotes";
    $topsubmitter = "select submittedby, count(*) as total from quotes group by submittedby order by count(*) desc limit 1";
    $totalUpvotesquery = "SELECT SUM(upvotes) as upvotes FROM quotes";
    $totalDownvotesquery = "SELECT SUM(downvotes) as downvotes FROM quotes";
    $countresult = mysqli_query($dbconnection,$countquery);
    $totalsubmittersresult = mysqli_query($dbconnection,$totalsubmittersquery);
    $topresult = mysqli_query($dbconnection,$topsubmitter);
    $totalUpvotesresult = mysqli_query($dbconnection,$totalUpvotesquery);
    $totalDownvotesresult = mysqli_query($dbconnection,$totalDownvotesquery);
    if(mysqli_num_rows($countresult) == 1) {
        while($row = mysqli_fetch_assoc($countresult)) {
            $totalQuotes = $row['total'];
        }
        while($row = mysqli_fetch_assoc($totalsubmittersresult)) {
            $totalSubmitters = $row['users'];
        }
        while($row = mysqli_fetch_assoc($totalUpvotesresult)) {
            $totalUpvotes = $row['upvotes'];
        }
        while($row = mysqli_fetch_assoc($totalDownvotesresult)) {
            $totalDownvotes = $row['downvotes'];
            $totalDownvotes = str_replace("-","",$totalDownvotes);
        }
        while($row = mysqli_fetch_assoc($topresult)) {
            $submittedby = $row['submittedby'];
            $total = $row['total'];
        }
        $topPercentage = ( ($total / $totalQuotes) * 100 );
        $topPercentage = round($topPercentage,2);
        $message = "".$data['usernickname'].": There are ".$totalQuotes." total quotes in the database, submitted by ".$totalSubmitters." unique usernames. A total of ".$totalUpvotes." upvotes and ".$totalDownvotes." downvotes have been counted. The user ".$submittedby." has submitted the most quotes at ".$total." - a total of ".$topPercentage."% of all quotes!";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}